<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Port;

use CODEHeures\Scrutineer\Model\ScrutineerContext;

/**
 * Host-implemented (re)building of a scenario's pre-arranged data — its "decor".
 *
 * The DECOR is everything a scenario needs to exist BEFORE the tester plays it: the records,
 * accounts, or documents left in exactly the state the steps assume. Scrutineer is
 * domain-agnostic — it never knows what your decor is made of. When a tester (re)loads a
 * scenario, the library calls {@see self::purge()} then {@see self::seed()}; you materialise
 * (or tear down) that decor however you see fit, on your own domain.
 *
 * ISOLATION is YOUR policy, not the library's: whether the decor for one tester (`actorRef`) is
 * shared with the others or isolated (e.g. one scope per actor) is decided HERE. Both calls
 * receive the {@see ScrutineerContext} (actorRef, scopeKey, role, appVersion) so you can scope
 * the decor to the tester; the library makes no cross-actor assumption — it only forwards the
 * context.
 */
interface ScenarioSeeder
{
    /** Build the decor for $scenarioId so the tester finds the state "ready to fire". */
    public function seed(string $scenarioId, ScrutineerContext $context): void;

    /** Tear the decor down — called before seed() on a reset, so seeding starts from clean. */
    public function purge(string $scenarioId, ScrutineerContext $context): void;
}
