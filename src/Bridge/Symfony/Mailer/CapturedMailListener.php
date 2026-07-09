<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Symfony\Mailer;

use CODEHeures\Scrutineer\Console\PosteToken;
use CODEHeures\Scrutineer\Model\CapturedMail;
use CODEHeures\Scrutineer\Port\MailStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Uid\Uuid;

/**
 * The single chokepoint of the mail-capture feature — registered ONLY when the host turns
 * `scrutineer.mail_capture.enabled` on (so a host that manages its own mail is untouched).
 *
 * On EVERY outgoing mail it strips the `X-Scrutineer-*` headers, so the poste token can never
 * ride out on a delivered mail — that guarantee is what lets one token be both the read
 * capability AND the scope key without leaking. Then, for a mail whose recipients ALL fall
 * under the reserved domain (e.g. `@scrutineer.invalid`), it captures a rendered snapshot and
 * {@see MessageEvent::reject()}s the send: staging personas have no real mailbox, so the
 * tester reads the 2FA code / invitation link from the console instead.
 *
 * It acts on the REAL send only (not the queued event): the poste header must survive the
 * Messenger hop from the web request to the worker where the send happens, so it is read —
 * and stripped — there, at the last moment.
 */
final class CapturedMailListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly MailStore $mails,
        private readonly string $domain,
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Very low priority so the Twig body renderer (default priority) has already turned a
        // TemplatedEmail into html/text by the time we snapshot it.
        return [MessageEvent::class => ['onMessage', -1000]];
    }

    public function onMessage(MessageEvent $event): void
    {
        // The queued event fires before the message is serialized onto the bus; leaving the
        // poste header intact there is what lets it reach the worker's real send below.
        if ($event->isQueued()) {
            return;
        }

        $message = $event->getMessage();
        if (!$message instanceof Email) {
            return;
        }

        $headers = $message->getHeaders();

        // Read the token, then strip on 100% of mails — captured or genuinely delivered.
        $rawToken = '';
        if ($headers->has(PosteToken::MAIL_HEADER)) {
            $rawToken = trim($headers->get(PosteToken::MAIL_HEADER)?->getBodyAsString() ?? '');
        }
        $this->stripScrutineerHeaders($headers);

        $recipients = $event->getEnvelope()->getRecipients();
        if (!$this->allUnderReservedDomain($recipients)) {
            return; // a real recipient in the mix → deliver normally (header already gone)
        }

        $this->mails->capture(new CapturedMail(
            id: Uuid::v7()->toRfc4122(),
            capturedAt: new \DateTimeImmutable(),
            posteHash: PosteToken::hash($rawToken),
            from: $this->firstAddress($message->getFrom()),
            to: array_values(array_map(static fn(Address $a): string => $a->getAddress(), $recipients)),
            subject: (string) $message->getSubject(),
            htmlBody: $this->bodyToString($message->getHtmlBody()),
            textBody: $this->bodyToString($message->getTextBody()),
        ));

        $event->reject();
    }

    private function stripScrutineerHeaders(Headers $headers): void
    {
        foreach ($headers->all() as $header) {
            if (str_starts_with(strtolower($header->getName()), 'x-scrutineer-')) {
                $headers->remove($header->getName());
            }
        }
    }

    /**
     * @param list<Address> $recipients
     */
    private function allUnderReservedDomain(array $recipients): bool
    {
        if ([] === $recipients) {
            return false;
        }
        $suffix = '@' . strtolower($this->domain);
        foreach ($recipients as $recipient) {
            if (!str_ends_with(strtolower($recipient->getAddress()), $suffix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Address> $addresses
     */
    private function firstAddress(array $addresses): string
    {
        return isset($addresses[0]) ? $addresses[0]->getAddress() : '';
    }

    private function bodyToString(mixed $body): ?string
    {
        if (null === $body) {
            return null;
        }
        if (\is_resource($body)) {
            $contents = stream_get_contents($body);

            return false === $contents ? null : $contents;
        }

        return (string) $body;
    }
}
