<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/**
 * Baseline outcome vocabulary. Deliberately NOT a closed enum: the outcome set is an
 * OPEN, contract-declared vocabulary that a host may extend (e.g. "blocked"). These are
 * the conventional values the console styles by default — {@see ScrutineerTestEvent::$outcome}
 * stays a plain string so the set can grow without a breaking change.
 */
final class Outcome
{
    public const CONFORME = 'conforme';
    public const NON_CONFORME = 'non-conforme';

    private function __construct() {}
}
