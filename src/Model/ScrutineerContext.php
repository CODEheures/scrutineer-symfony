<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * The resolved context handed to {@see \CODEHeures\Scrutineer\Port\ScenarioSeeder} for a single
 * operation. The host's seeder reads {@see $actorRef} to apply its chosen isolation.
 */
final readonly class ScrutineerContext
{
    public function __construct(
        public string $actorRef,
        public ?string $scopeKey = null,
        public ?string $role = null,
        public ?string $appVersion = null,
    ) {}
}
