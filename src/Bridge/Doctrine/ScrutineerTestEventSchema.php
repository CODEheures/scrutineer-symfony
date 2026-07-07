<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The default ledger schema the library ships (the host may override the store via the
 * {@see \CODEHeures\Scrutineer\Port\HistoryStore} port). The host APPLIES this schema (a
 * migration, or an auto-create) wherever it wants — e.g. a dedicated schema of its own.
 *
 * Deliberately FK-free and identifier-opaque: that is what keeps it host-agnostic, and it
 * is also exactly what gives the host "survives a decor reset" and "no PII" for free.
 */
final class ScrutineerTestEventSchema
{
    public const TABLE = 'scrutineer_test_event';

    private function __construct() {}

    /** Declares the append-only ledger table on the given DBAL schema. */
    public static function define(Schema $schema, string $table = self::TABLE): Table
    {
        $t = $schema->createTable($table);
        $t->addColumn('id', Types::GUID);
        $t->addColumn('occurred_at', Types::DATETIME_IMMUTABLE);
        $t->addColumn('app_version', Types::STRING, ['length' => 64]);
        $t->addColumn('actor_ref', Types::STRING, ['length' => 255]); // opaque, no PII, no FK
        $t->addColumn('scenario_id', Types::STRING, ['length' => 255]);
        $t->addColumn('outcome', Types::STRING, ['length' => 64]);
        $t->addColumn('comment', Types::TEXT, ['notnull' => false]);
        $t->addColumn('scope_key', Types::STRING, ['length' => 255, 'notnull' => false]); // opaque, no FK

        $t->setPrimaryKey(['id']);
        $t->addIndex(['scenario_id']);
        $t->addIndex(['actor_ref']);
        $t->addIndex(['app_version']);
        $t->addIndex(['scope_key']);
        $t->addIndex(['occurred_at']);

        return $t;
    }
}
