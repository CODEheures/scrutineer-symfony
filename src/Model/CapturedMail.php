<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * One mail the library intercepted instead of delivering — because all its recipients fell
 * under the reserved capture domain (e.g. `@scrutineer.invalid`). It is a rendered snapshot:
 * subject + bodies as they would have been sent, so a tester reads the 2FA code / invitation
 * link straight from the console instead of a mailbox that does not exist in staging.
 *
 * `posteHash` scopes it to the browser that triggered the flow ({@see \CODEHeures\Scrutineer\Console\PosteToken});
 * '' means the host stamped no poste header, so no token can read it back.
 */
final readonly class CapturedMail
{
    /**
     * @param list<string> $to the envelope recipients (all under the reserved domain)
     */
    public function __construct(
        public string $id,
        public \DateTimeImmutable $capturedAt,
        public string $posteHash,
        public string $from,
        public array $to,
        public string $subject,
        public ?string $htmlBody,
        public ?string $textBody,
    ) {}
}
