<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * One immutable test-result fact, stored append-only (never updated).
 *
 * Carries only opaque/structured data and NO PII: the tester is an opaque {@see $actorRef}
 * resolved to a human label elsewhere, at render time. Human rendering of the scenario
 * (its label) comes from the host-declared catalogue, not from here.
 */
final readonly class ScrutineerTestEvent
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $occurredAt,
        public string $appVersion,
        public string $actorRef,
        public string $scenarioId,
        /** Open vocabulary; see {@see Outcome} for the baseline values. */
        public string $outcome,
        public ?string $comment = null,
        public ?string $scopeKey = null,
    ) {}
}
