<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use CODEHeures\Scrutineer\Model\HistoryQuery;
use CODEHeures\Scrutineer\Model\ScrutineerTestEvent;
use CODEHeures\Scrutineer\Port\HistoryStore;

/**
 * The default {@see HistoryStore}: a DBAL-backed, append-only ledger. INSERT-only by
 * design — no update, no delete — so results stay an immutable history. The connection is
 * host-chosen (e.g. a `shared` connection); the table defaults to
 * {@see ScrutineerTestEventSchema::TABLE}.
 */
final class DoctrineHistoryStore implements HistoryStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = ScrutineerTestEventSchema::TABLE,
    ) {}

    public function append(ScrutineerTestEvent $event): void
    {
        $this->connection->insert($this->table, [
            'id' => $event->id,
            'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s.u'),
            'app_version' => $event->appVersion,
            'actor_ref' => $event->actorRef,
            'scenario_id' => $event->scenarioId,
            'outcome' => $event->outcome,
            'comment' => $event->comment,
            'scope_key' => $event->scopeKey,
        ]);
    }

    public function timeline(HistoryQuery $query): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('id', 'occurred_at', 'app_version', 'actor_ref', 'scenario_id', 'outcome', 'comment', 'scope_key')
            ->from($this->table)
            ->orderBy('occurred_at', 'DESC')
            ->addOrderBy('id', 'DESC');

        $this->applyFilter($qb, 'scenario_id', $query->scenarioId);
        $this->applyFilter($qb, 'app_version', $query->appVersion);
        $this->applyFilter($qb, 'actor_ref', $query->actorRef);
        $this->applyFilter($qb, 'scope_key', $query->scopeKey);

        if (null !== $query->limit) {
            $qb->setMaxResults($query->limit);
        }
        if (null !== $query->offset) {
            $qb->setFirstResult($query->offset);
        }

        return array_map(
            fn(array $row): ScrutineerTestEvent => $this->hydrate($row),
            $qb->executeQuery()->fetchAllAssociative(),
        );
    }

    private function applyFilter(QueryBuilder $qb, string $column, ?string $value): void
    {
        if (null === $value) {
            return;
        }
        $param = 'p_' . $column;
        $qb->andWhere(\sprintf('%s = :%s', $column, $param))->setParameter($param, $value);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ScrutineerTestEvent
    {
        return new ScrutineerTestEvent(
            id: self::str($row['id']),
            occurredAt: new \DateTimeImmutable(self::str($row['occurred_at'])),
            appVersion: self::str($row['app_version']),
            actorRef: self::str($row['actor_ref']),
            scenarioId: self::str($row['scenario_id']),
            outcome: self::str($row['outcome']),
            comment: null !== $row['comment'] ? self::str($row['comment']) : null,
            scopeKey: null !== $row['scope_key'] ? self::str($row['scope_key']) : null,
        );
    }

    /** Coerce a DBAL row value (mixed) to string; a non-scalar degrades to ''. */
    private static function str(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
