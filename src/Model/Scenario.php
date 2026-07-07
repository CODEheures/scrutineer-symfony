<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * A scenario descriptor — the atomic unit of acceptance testing: it carries its own
 * pre-seeded decor, an optional required role, an entry point and steps, and yields ONE
 * outcome per run. Mirrors scenario.schema.json (in the scrutineer-front contract).
 *
 * `introducedIn` groups scenarios by the release that added them; `status` (+ `obsoleteSince`)
 * lets a superseded scenario stay listed while greyed out; `lot` names the isolated test
 * scope it belongs to (the host maps `lot → scope` for the reset).
 */
final readonly class Scenario
{
    /**
     * @param list<string> $steps ordered actions the tester must perform
     */
    public function __construct(
        public string $id,
        public string $label,
        public ?string $group = null,
        public ?string $role = null,
        public ?string $entryPoint = null,
        public array $steps = [],
        public ?string $expectedResult = null,
        public ?Audience $audience = null,
        public ?string $description = null,
        public ?string $introducedIn = null,
        public ScenarioStatus $status = ScenarioStatus::Active,
        public ?string $obsoleteSince = null,
        public ?string $lot = null,
    ) {}
}
