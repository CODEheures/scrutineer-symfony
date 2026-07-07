<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * Filter for {@see \CODEHeures\Scrutineer\Port\HistoryStore::timeline()}. A null field means
 * "no constraint on this axis".
 */
final readonly class HistoryQuery
{
    public function __construct(
        public ?string $scenarioId = null,
        public ?string $appVersion = null,
        public ?string $actorRef = null,
        public ?string $scopeKey = null,
        public ?int $limit = null,
        public ?int $offset = null,
    ) {}
}
