<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Port;

/**
 * Optional host adapter that turns an opaque "actor reference" into a human-readable label,
 * FOR DISPLAY ONLY.
 *
 * An ACTOR is whoever performs an acceptance test — a tester (a real person using your app in a
 * test context). The library never stores their identity: it stamps each result with an opaque
 * `actorRef` (a host-defined handle, typically the user id — no PII, see
 * {@see ScrutineerContextProvider::actorRef()}). This port resolves those refs back to labels
 * (e.g. "Ada Lovelace") LIVE, at render time — never a stored snapshot: a deleted or renamed
 * tester simply yields a placeholder or the new label. That live resolution is the deliberate
 * trade-off that keeps the immutable ledger PII-free.
 *
 * The port is optional: without it, the console shows the raw refs.
 */
interface ActorResolver
{
    /**
     * @param  list<string>          $actorRefs the opaque refs seen in the history to be labelled
     * @return array<string, string> map of actorRef => display label; a ref you cannot resolve
     *                               may be omitted (the library falls back to the raw ref)
     */
    public function resolve(array $actorRefs): array;
}
