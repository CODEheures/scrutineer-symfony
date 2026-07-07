<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Port;

use CODEHeures\Scrutineer\Model\PublishResult;

/**
 * OPTIONAL port: publish the current non-conformities to an external tracker (an issue tracker,
 * a bug board…). A NON-CONFORMITY is a scenario whose latest recorded result is a failing
 * outcome — the results you'd want to raise a ticket for.
 *
 * Publishing to a ticketing system is a HOST responsibility, not the library's: the lib
 * only forwards the request (contract endpoint `POST /publish`). A host that does not wire a
 * publisher leaves {@see \CODEHeures\Scrutineer\Console\ScrutineerConsole::publish()} answering
 * {@see PublishResult::unsupported()} — the call is accepted by the contract but not handled.
 *
 * Implementations SHOULD be idempotent (dedupe by scenario + outcome + date) so re-publishing
 * does not create duplicate tickets.
 */
interface TicketPublisher
{
    /**
     * @param array<string, mixed> $filter host-interpreted narrowing (e.g. release, scopeKey)
     */
    public function publish(array $filter): PublishResult;
}
