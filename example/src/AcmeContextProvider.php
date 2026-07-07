<?php

declare(strict_types=1);

namespace Acme;

use CODEHeures\Scrutineer\Port\ScrutineerContextProvider;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * REQUIRED port. Tells the library who is acting and in which scope. In a real host these come
 * from your security/session + tenancy; here they are demo constants. `actorRef` is OPAQUE —
 * never PII (the human label is resolved live by {@see AcmeActorResolver}).
 */
#[AsAlias(ScrutineerContextProvider::class)]
final class AcmeContextProvider implements ScrutineerContextProvider
{
    public function actorRef(): ?string
    {
        return 'member-42'; // e.g. the current user id — opaque, no PII
    }

    public function role(): ?string
    {
        return 'member'; // the current user's role (drives the catalogue filter)
    }

    public function appVersion(): string
    {
        return '1.4.0'; // e.g. your current release tag
    }

    public function isScrutineerContext(): bool
    {
        // The host decides what marks a test context (a slug prefix, a session flag…).
        return str_starts_with((string) $this->scopeKey(), 'qa-');
    }

    public function scopeKey(): ?string
    {
        return 'qa-central-branch'; // opaque scope key (a branch, a workspace, an org…)
    }
}
