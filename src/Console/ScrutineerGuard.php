<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Console;

/**
 * The single server-side barrier: the library is reachable only when the host switched it
 * on via its environment (`SCRUTINEER_ENABLED`). Framework-agnostic; the transport layer
 * turns a denial into a 403.
 *
 * Authentication and "who may play what" are HOST concerns, layered on top by the host's
 * security config: the catalogue is readable unauthenticated (so a
 * tester can read "log in as X" on the login screen), while recording / resetting requires
 * a logged-in test user. The env-var flag alone grants no data — it only lifts the shutter.
 */
final class ScrutineerGuard
{
    public function __construct(
        private readonly bool $enabled,
    ) {}

    /**
     * Whether the library is switched on at all. In prod this is off, so the asset and every
     * endpoint refuse even if someone forces their client-side activation flag.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
