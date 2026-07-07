<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Port;

/**
 * Host adapter that answers, for the CURRENT request: WHO is testing, with WHAT role, on WHICH
 * application version, in WHICH scope — and whether this is a test context at all.
 *
 * These are all HOST concepts (identity, authorisation, versioning, tenancy/scoping) that
 * Scrutineer cannot know: you read them from your own security/session and hand them over. The
 * library uses them to filter the catalogue by role, gate every route, and stamp each recorded
 * result.
 */
interface ScrutineerContextProvider
{
    /**
     * Opaque, host-defined handle for the current tester (the "actor") — typically the user id.
     * MUST NOT carry PII; the human label is resolved separately ({@see ActorResolver}). Returns
     * null when nobody is authenticated.
     */
    public function actorRef(): ?string;

    /**
     * The current tester's authorisation role in YOUR application (e.g. "admin", "member"). It is
     * handed to {@see CatalogProvider::scenarios()} to decide which scenarios that tester may
     * play. Returns null when the tester has no role, or when you do not gate scenarios by role.
     */
    public function role(): ?string;

    /**
     * The version of the application currently under test — e.g. your current release tag
     * ("1.4.0"). It is stamped on every result, so a verdict is tied to the version it was
     * observed on (and "OK in 1.3 / KO in 1.4" falls straight out of the history).
     */
    public function appVersion(): string;

    /**
     * Whether the current request is a TEST (acceptance) context — a host decision (e.g. the
     * current scope's slug carries a known prefix, or a session flag is set). When false, the
     * console and its routes stay inert even if the feature is enabled.
     */
    public function isScrutineerContext(): bool;

    /**
     * Opaque key of the SCOPE the test runs in — the isolated area of your app a run is bound to
     * (a workspace, a tenant, a branch, an org…). It is stamped on results so history can be
     * filtered by scope. Returns null when a run is not scoped to any.
     */
    public function scopeKey(): ?string;
}
