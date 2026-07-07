<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * Lifecycle of a scenario in the host catalogue. `obsolete` is DECLARED IN CODE by the dev
 * who ships the release that kills the tested feature — the scenario stays listed (so its
 * historical verdicts remain readable) but is greyed out / filterable in the console.
 */
enum ScenarioStatus: string
{
    case Active = 'active';
    case Obsolete = 'obsolete';
}
