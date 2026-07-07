<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * Outcome of a {@see \CODEHeures\Scrutineer\Port\TicketPublisher::publish()} call — whether the host
 * pushed the non-conformities to its tracker, or does not handle ticketing at all.
 */
final readonly class PublishResult
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_UNSUPPORTED = 'unsupported';

    public function __construct(
        public string $status,
        public string $message,
        public int $count = 0,
    ) {}

    /** The default when no host publisher is wired: the request is accepted but not handled. */
    public static function unsupported(string $message = 'Ticketing publication is not handled by this host.'): self
    {
        return new self(self::STATUS_UNSUPPORTED, $message, 0);
    }

    public static function published(int $count, string $message = 'Non-conformities published.'): self
    {
        return new self(self::STATUS_PUBLISHED, $message, $count);
    }
}
