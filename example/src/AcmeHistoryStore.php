<?php

declare(strict_types=1);

namespace Acme;

use CODEHeures\Scrutineer\Model\HistoryQuery;
use CODEHeures\Scrutineer\Model\ScrutineerTestEvent;
use CODEHeures\Scrutineer\Port\HistoryStore;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * OPTIONAL port — a default Doctrine-backed store already ships with the library, so you only
 * implement this to use another backend. This in-memory version keeps the example runnable with
 * no database. APPEND-ONLY by design: never update, never delete, so the history stays immutable.
 */
#[AsAlias(HistoryStore::class)]
final class AcmeHistoryStore implements HistoryStore
{
    /** @var list<ScrutineerTestEvent> */
    private array $events = [];

    public function append(ScrutineerTestEvent $event): void
    {
        $this->events[] = $event; // INSERT-only
    }

    /** @return list<ScrutineerTestEvent> */
    public function timeline(HistoryQuery $query): array
    {
        $rows = array_filter($this->events, static fn (ScrutineerTestEvent $e): bool =>
            (null === $query->scenarioId || $e->scenarioId === $query->scenarioId)
            && (null === $query->appVersion || $e->appVersion === $query->appVersion)
            && (null === $query->actorRef || $e->actorRef === $query->actorRef)
            && (null === $query->scopeKey || $e->scopeKey === $query->scopeKey));

        // Most recent first.
        usort($rows, static fn (ScrutineerTestEvent $a, ScrutineerTestEvent $b): int => $b->occurredAt <=> $a->occurredAt);

        return array_values(array_slice($rows, $query->offset ?? 0, $query->limit));
    }
}
