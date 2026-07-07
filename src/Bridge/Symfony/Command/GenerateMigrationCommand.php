<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Symfony\Command;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\DependencyFactory;
use CODEHeures\Scrutineer\Bridge\Doctrine\ScrutineerTestEventSchema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates a Doctrine migration that creates the append-only ledger table (the ONE schema
 * object the library owns — {@see ScrutineerTestEventSchema}). The DDL is derived from the
 * library mapping and the configured table name; the file is written through the host's own
 * {@see DependencyFactory}, so it lands wherever the host configured its migrations
 * (`migrations_paths`, namespace, `organize_migrations`) — the library hardcodes no path.
 *
 * The host then applies it with its normal `doctrine:migrations:migrate` (e.g. on deploy):
 * the library pilots the WHAT (the mapping), the host the WHERE (its migration pipeline).
 *
 *   php bin/console scrutineer:generate-migration
 *
 * Requires doctrine/doctrine-migrations-bundle (a `suggest`, not a hard dependency): absent,
 * the DependencyFactory is unbound and the command fails with a clear message.
 */
#[AsCommand(
    name: 'scrutineer:generate-migration',
    description: 'Generate a Doctrine migration for the Scrutineer ledger table, into the host migrations path.',
)]
final class GenerateMigrationCommand extends Command
{
    public function __construct(
        private readonly string $table = ScrutineerTestEventSchema::TABLE,
        private readonly ?DependencyFactory $dependencyFactory = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            'Migrations namespace to generate into (defaults to the host\'s first configured namespace).',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null === $this->dependencyFactory) {
            $io->error(
                'doctrine/doctrine-migrations-bundle is required to generate a migration. '
                . 'Install it, or apply ScrutineerTestEventSchema::define() by hand.',
            );

            return Command::FAILURE;
        }

        // DDL for the host's migration platform (the migration runs on that connection).
        $platform = $this->dependencyFactory->getConnection()->getDatabasePlatform();
        $schema = new Schema();
        ScrutineerTestEventSchema::define($schema, $this->table);

        // The library owns exactly the ledger TABLE. When the table is schema-qualified,
        // DBAL also wants to CREATE SCHEMA — but schema lifecycle is the host's concern (its
        // schema usually pre-exists), so drop those statements and just flag the assumption.
        $statements = $schema->toSql($platform);
        $schemaStatements = array_values(array_filter($statements, self::isCreateSchema(...)));
        $tableStatements = array_values(array_filter($statements, static fn(string $sql): bool => !self::isCreateSchema($sql)));
        if ([] !== $schemaStatements) {
            $io->note(\sprintf(
                'Skipped %d CREATE SCHEMA statement(s): the host owns schema lifecycle — ensure the target schema exists before migrating.',
                \count($schemaStatements),
            ));
        }

        $up = $this->render($tableStatements);
        $down = $this->render([$platform->getDropTableSQL($this->table)]);

        // Pick the target namespace from the host's own migrations configuration.
        $namespace = $this->strOpt($input, 'namespace');
        if (null === $namespace) {
            $directories = $this->dependencyFactory->getConfiguration()->getMigrationDirectories();
            $namespace = array_key_first($directories);
        }
        if (null === $namespace) {
            $io->error('No migrations namespace configured on the host (doctrine_migrations.migrations_paths).');

            return Command::FAILURE;
        }

        $fqcn = $this->dependencyFactory->getClassNameGenerator()->generateClassName($namespace);
        $path = $this->dependencyFactory->getMigrationGenerator()->generateMigration($fqcn, $up, $down);

        $io->success(\sprintf('Generated migration for table "%s": %s', $this->table, $path));

        return Command::SUCCESS;
    }

    /**
     * Render a list of SQL statements as `$this->addSql(...)` lines (no indentation — the
     * Doctrine generator indents the body itself).
     *
     * @param list<string> $statements
     */
    private function render(array $statements): string
    {
        return implode("\n", array_map(
            static fn(string $sql): string => \sprintf('$this->addSql(%s);', var_export($sql, true)),
            $statements,
        ));
    }

    private static function isCreateSchema(string $sql): bool
    {
        return 1 === preg_match('/^\s*CREATE\s+SCHEMA\b/i', $sql);
    }

    private function strOpt(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
