<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Symfony;

use CODEHeures\Scrutineer\Bridge\Doctrine\DoctrineHistoryStore;
use CODEHeures\Scrutineer\Bridge\Doctrine\ScrutineerTestEventSchema;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The Symfony integration of Scrutineer. Configuration is `.env`-driven on the host side;
 * the bundle only exposes the keys with safe defaults (disabled unless explicitly turned
 * on). Routes are shipped without a prefix — the host imports them under
 * `SCRUTINEER_ROUTE_PREFIX`.
 *
 * Storage is config-driven too: `table` names the ledger table and `connection` selects
 * which DBAL connection hosts it — so a host installs the library by configuration alone
 * (see `scrutineer:generate-migration` for the matching migration).
 */
final class ScrutineerBundle extends AbstractBundle
{
    /**
     * This bundle class lives in `src/Bridge/Symfony/`, but `AbstractBundle::getPath()` derives
     * the bundle root as `dirname(<class file>, 2)` = `src/Bridge`. The `@ScrutineerBundle`
     * resource locator would then look under `src/Bridge/Resources/…`, which does not exist — the
     * Resources live beside this file, in `src/Bridge/Symfony/Resources/…`. Point the bundle path
     * at this file's own directory so hosts can mount routes with the documented
     * `@ScrutineerBundle/Resources/config/routes.php` locator.
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('asset_path')->defaultNull()->end()
                // Default language for the library's own strings (CSV export headers). The
                // console front picks its language per request via the <scrutineer-console
                // lang> attribute; this is the fallback for the CLI export.
                ->scalarNode('language')->defaultValue('en')->end()
                // The append-only ledger table the library owns. May be schema-qualified
                // (e.g. "shared.scrutineer_test_event") when the platform supports it.
                ->scalarNode('table')->defaultValue(ScrutineerTestEventSchema::TABLE)->end()
                // The DBAL connection hosting the ledger; null = the default connection.
                // Should match the connection the host runs its migrations against.
                ->scalarNode('connection')->defaultNull()->end()
            ->end();
    }

    /**
     * @param array{
     *     enabled: bool,
     *     asset_path: string|null,
     *     language: string,
     *     table: string,
     *     connection: string|null,
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()
            ->set('scrutineer.enabled', $config['enabled'])
            ->set('scrutineer.asset_path', $config['asset_path'])
            ->set('scrutineer.language', $config['language'])
            ->set('scrutineer.table', $config['table']);

        $container->import(__DIR__ . '/Resources/config/services.php');

        // Bind the ledger to a named DBAL connection if the host chose one (else the store
        // autowires the default connection). Done on the builder so it overrides the
        // autowired `$connection` argument of the already-imported store definition.
        if (null !== $config['connection']) {
            $builder->getDefinition(DoctrineHistoryStore::class)
                ->setArgument('$connection', new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection'])));
        }
    }
}
