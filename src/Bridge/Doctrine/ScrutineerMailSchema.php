<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The default schema for the captured-mail inbox (the second — and only other — table the
 * library owns, beside {@see ScrutineerTestEventSchema}). The host APPLIES it wherever it
 * keeps the ledger (a migration, an auto-create), only when it turns mail capture on.
 *
 * FK-free and PII-light by design: it holds staging mail (2FA codes / invitation links for
 * SEEDED test personas), scoped by an opaque `poste_hash` — no link to any real user.
 */
final class ScrutineerMailSchema
{
    public const TABLE = 'scrutineer_captured_mail';

    private function __construct() {}

    /** Declares the captured-mail table on the given DBAL schema. */
    public static function define(Schema $schema, string $table = self::TABLE): Table
    {
        $t = $schema->createTable($table);
        $t->addColumn('id', Types::GUID);
        $t->addColumn('captured_at', Types::DATETIME_IMMUTABLE);
        $t->addColumn('poste_hash', Types::STRING, ['length' => 64]); // sha256 hex of the poste token; '' = untagged
        $t->addColumn('from_address', Types::STRING, ['length' => 255]);
        $t->addColumn('to_addresses', Types::TEXT); // comma-joined envelope recipients
        $t->addColumn('subject', Types::TEXT);
        $t->addColumn('html_body', Types::TEXT, ['notnull' => false]);
        $t->addColumn('text_body', Types::TEXT, ['notnull' => false]);

        $t->setPrimaryKey(['id']);
        $t->addIndex(['poste_hash']);
        $t->addIndex(['captured_at']);

        return $t;
    }
}
