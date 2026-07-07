<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Port;

use CODEHeures\Scrutineer\Model\HistoryQuery;
use CODEHeures\Scrutineer\Model\ScrutineerTestEvent;

/**
 * Append-only ledger of test-result events. One EVENT is a fact: "scenario X was found
 * <outcome> on version Y by actor Z (in scope S), at time T".
 *
 * The library ships a default Doctrine-backed implementation; you only implement this port to
 * keep the history somewhere else (another database, an API…). It is deliberately INSERT-only —
 * no update, no delete — so the history stays an immutable trail of facts, never an overwritten
 * status. A scenario's CURRENT status is therefore a PROJECTION over this ledger (its most
 * recent matching event), computed by the caller — not a column you keep up to date.
 */
interface HistoryStore
{
    /** Record one result event. INSERT-only — never mutate a past event. */
    public function append(ScrutineerTestEvent $event): void;

    /**
     * @return list<ScrutineerTestEvent> the events matching $query, most-recent-first
     */
    public function timeline(HistoryQuery $query): array;
}
