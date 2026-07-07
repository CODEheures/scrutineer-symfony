<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Console;

use CODEHeures\Scrutineer\Model\HistoryQuery;
use CODEHeures\Scrutineer\Model\PublishResult;
use CODEHeures\Scrutineer\Model\Scenario;
use CODEHeures\Scrutineer\Model\ScrutineerContext;
use CODEHeures\Scrutineer\Model\ScrutineerTestEvent;
use CODEHeures\Scrutineer\Port\ActorResolver;
use CODEHeures\Scrutineer\Port\CatalogProvider;
use CODEHeures\Scrutineer\Port\HistoryStore;
use CODEHeures\Scrutineer\Port\ScenarioSeeder;
use CODEHeures\Scrutineer\Port\ScrutineerContextProvider;
use CODEHeures\Scrutineer\Port\TicketPublisher;
use Symfony\Component\Uid\Uuid;

/**
 * The framework-agnostic orchestrator: it wires the ports together to serve the catalogue,
 * reset a decor, read the history and record results. It produces contract-shaped arrays
 * (the library's own public surface) so the framework bridge stays a thin transport.
 *
 * Pure PHP (+ symfony/uid for ids); no Symfony nor Doctrine here — this is what a Python
 * back would mirror.
 */
final class ScrutineerConsole
{
    public function __construct(
        private readonly ScenarioSeeder $seeder,
        private readonly ScrutineerContextProvider $context,
        private readonly CatalogProvider $catalog,
        private readonly HistoryStore $history,
        private readonly ?ActorResolver $actorResolver = null,
        private readonly ?TicketPublisher $ticketPublisher = null,
    ) {}

    /**
     * @return array{context: array<string, mixed>, scenarios: list<array<string, mixed>>, actors: array<string, string>}
     */
    public function catalog(): array
    {
        $role = $this->context->role();

        $items = [];
        $refs = [];
        foreach ($this->catalog->scenarios($role) as $scenario) {
            // Global verdict: a scenario's current verdict is its SINGLE most
            // recent event — whoever recorded it, in whatever release. Not filtered by actorRef
            // (every tester sees the same status, whoever recorded it) nor by
            // appVersion (a verdict reports forward from release to release). "Not tested" =
            // no event at all; the event carries its own release for a possible "recheck" hint.
            $last = $this->history->timeline(new HistoryQuery(
                scenarioId: $scenario->id,
                limit: 1,
            ))[0] ?? null;

            if (null !== $last) {
                $refs[$last->actorRef] = true;
            }
            $items[] = $this->scenarioToArray($scenario, $last);
        }

        return [
            'context' => $this->contextToArray(),
            'scenarios' => $items,
            'actors' => $this->resolveActors(array_keys($refs)),
        ];
    }

    public function reset(string $scenarioId): void
    {
        $ctx = $this->currentContext();
        $this->seeder->purge($scenarioId, $ctx);
        $this->seeder->seed($scenarioId, $ctx);
    }

    /**
     * @return array{events: list<array<string, mixed>>, actors: array<string, string>}
     */
    public function history(HistoryQuery $query): array
    {
        $events = [];
        $refs = [];
        foreach ($this->history->timeline($query) as $event) {
            $refs[$event->actorRef] = true;
            $events[] = $this->eventToArray($event);
        }

        return ['events' => $events, 'actors' => $this->resolveActors(array_keys($refs))];
    }

    /**
     * Forwards a "publish the non-conformities to ticketing" request to the host's optional
     * {@see TicketPublisher}. No publisher wired → "unsupported" (accepted, not handled): the
     * lib never publishes anything itself (ticketing is a host concern).
     *
     * @param array<string, mixed> $filter
     *
     * @return array{status: string, message: string, count: int}
     */
    public function publish(array $filter): array
    {
        $result = $this->ticketPublisher?->publish($filter) ?? PublishResult::unsupported();

        return ['status' => $result->status, 'message' => $result->message, 'count' => $result->count];
    }

    public function record(string $scenarioId, string $outcome, ?string $comment): ScrutineerTestEvent
    {
        $event = new ScrutineerTestEvent(
            id: Uuid::v7()->toRfc4122(),
            occurredAt: new \DateTimeImmutable(),
            appVersion: $this->context->appVersion(),
            actorRef: $this->context->actorRef() ?? '',
            scenarioId: $scenarioId,
            outcome: $outcome,
            comment: $comment,
            scopeKey: $this->context->scopeKey(),
        );
        $this->history->append($event);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    public function eventToArray(ScrutineerTestEvent $event): array
    {
        return [
            'id' => $event->id,
            'occurredAt' => $event->occurredAt->format(\DATE_ATOM),
            'appVersion' => $event->appVersion,
            'actorRef' => $event->actorRef,
            'scenarioId' => $event->scenarioId,
            'outcome' => $event->outcome,
            'comment' => $event->comment,
            'scopeKey' => $event->scopeKey,
        ];
    }

    private function currentContext(): ScrutineerContext
    {
        return new ScrutineerContext(
            actorRef: $this->context->actorRef() ?? '',
            scopeKey: $this->context->scopeKey(),
            role: $this->context->role(),
            appVersion: $this->context->appVersion(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function scenarioToArray(Scenario $scenario, ?ScrutineerTestEvent $last): array
    {
        return [
            'id' => $scenario->id,
            'label' => $scenario->label,
            'group' => $scenario->group,
            'role' => $scenario->role,
            'entryPoint' => $scenario->entryPoint,
            'steps' => $scenario->steps,
            'expectedResult' => $scenario->expectedResult,
            'audience' => $scenario->audience?->value,
            'description' => $scenario->description,
            'introducedIn' => $scenario->introducedIn,
            'status' => $scenario->status->value,
            'obsoleteSince' => $scenario->obsoleteSince,
            'lot' => $scenario->lot,
            'lastEvent' => null !== $last ? $this->eventToArray($last) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contextToArray(): array
    {
        return [
            'actorRef' => $this->context->actorRef(),
            'role' => $this->context->role(),
            'appVersion' => $this->context->appVersion(),
            'scopeKey' => $this->context->scopeKey(),
        ];
    }

    /**
     * @param list<string> $refs
     *
     * @return array<string, string>
     */
    private function resolveActors(array $refs): array
    {
        if (null === $this->actorResolver || [] === $refs) {
            return [];
        }

        return $this->actorResolver->resolve($refs);
    }
}
