<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Console;

use CODEHeures\Scrutineer\Model\CapturedMail;
use CODEHeures\Scrutineer\Port\MailStore;

/**
 * Framework-agnostic reader for the captured-mail inbox — the mail counterpart of
 * {@see ScrutineerConsole}. It resolves the raw poste token to its hash and returns
 * contract-shaped arrays; the transport bridge stays thin.
 *
 * Only wired when mail capture is on ({@see \CODEHeures\Scrutineer\Port\MailStore}).
 */
final class ScrutineerMailbox
{
    public function __construct(
        private readonly MailStore $mails,
        private readonly int $retentionDays = 7,
    ) {}

    /**
     * Opportunistic GC: drop mails past the retention window. Called by the mint route, so the
     * inbox self-cleans every time a tester (re)opens the console — no host cron needed.
     */
    public function purgeExpired(): void
    {
        $this->mails->purge(new \DateTimeImmutable(\sprintf('-%d days', $this->retentionDays)));
    }

    /**
     * @return array{mails: list<array<string, mixed>>}
     */
    public function inbox(?string $rawToken, ?int $limit = null): array
    {
        $hash = PosteToken::hash((string) $rawToken);

        return [
            'mails' => array_map(
                fn(CapturedMail $mail): array => $this->toArray($mail),
                $this->mails->inbox($hash, $limit),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(CapturedMail $mail): array
    {
        return [
            'id' => $mail->id,
            'capturedAt' => $mail->capturedAt->format(\DATE_ATOM),
            'from' => $mail->from,
            'to' => $mail->to,
            'subject' => $mail->subject,
            'htmlBody' => $mail->htmlBody,
            'textBody' => $mail->textBody,
        ];
    }
}
