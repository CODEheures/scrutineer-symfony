<?php

declare(strict_types=1);

namespace Acme;

use CODEHeures\Scrutineer\Model\PublishResult;
use CODEHeures\Scrutineer\Port\TicketPublisher;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * OPTIONAL port. Pushes the current non-conformities to YOUR tracker. Not wiring it at all leaves
 * `POST /publish` answering {@see PublishResult::unsupported()}. Implementations SHOULD be
 * idempotent (dedupe by scenario + outcome + date) so re-publishing creates no duplicate tickets.
 */
#[AsAlias(TicketPublisher::class)]
final class AcmeTicketPublisher implements TicketPublisher
{
    /**
     * @param array<string, mixed> $filter host-interpreted narrowing (e.g. release, scopeKey)
     */
    public function publish(array $filter): PublishResult
    {
        // A real host would read its history for the non-conformities matching $filter and
        // create/update tickets in Jira, YouTrack, GitHub Issues… Here we just pretend two were
        // pushed. Returning unsupported() instead would say "this host does not do ticketing".
        $created = 2;

        return PublishResult::published($created, "$created non-conformité(s) publiée(s) vers le tracker.");
    }
}
