<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Bridge\Symfony;

use CODEHeures\Scrutineer\Bridge\Doctrine\DoctrineHistoryStore;
use CODEHeures\Scrutineer\Bridge\Doctrine\DoctrineMailStore;
use CODEHeures\Scrutineer\Bridge\Doctrine\ScrutineerMailSchema;
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
                // Mail capture: intercept mails to the reserved domain into a staging inbox
                // instead of delivering them (seeded personas have no real mailbox). OFF by
                // default — the library never touches a host's mailer unless asked. Requires
                // symfony/mailer on the host.
                ->arrayNode('mail_capture')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        // Recipients whose address ends with @<domain> are captured. Use a
                        // guaranteed-unroutable TLD (RFC 2606 `.invalid`) so a missed capture
                        // still cannot deliver anywhere.
                        ->scalarNode('domain')->defaultValue('scrutineer.invalid')->end()
                        ->scalarNode('table')->defaultValue(ScrutineerMailSchema::TABLE)->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{
     *     enabled: bool,
     *     asset_path: string|null,
     *     language: string,
     *     table: string,
     *     connection: string|null,
     *     mail_capture: array{enabled: bool, domain: string, table: string},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $mailCapture = $config['mail_capture'];

        $container->parameters()
            ->set('scrutineer.enabled', $config['enabled'])
            ->set('scrutineer.asset_path', $config['asset_path'])
            ->set('scrutineer.language', $config['language'])
            ->set('scrutineer.table', $config['table'])
            // Always set (even when off) so services.php / the command can reference them.
            ->set('scrutineer.mail_capture.enabled', $mailCapture['enabled'])
            ->set('scrutineer.mail_capture.domain', $mailCapture['domain'])
            ->set('scrutineer.mail_capture.table', $mailCapture['table']);

        $container->import(__DIR__ . '/Resources/config/services.php');

        // The mailer listener + inbox store are wired ONLY when capture is on, so a host that
        // manages its own mail is never decorated.
        if ($mailCapture['enabled']) {
            $container->import(__DIR__ . '/Resources/config/services_mail.php');
        }

        // Bind the ledger (and the inbox, when wired) to a named DBAL connection if the host
        // chose one — else they autowire the default. Done on the builder so it overrides the
        // autowired `$connection` argument of the already-imported store definitions.
        if (null !== $config['connection']) {
            $connection = new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection']));
            $builder->getDefinition(DoctrineHistoryStore::class)->setArgument('$connection', $connection);
            if ($mailCapture['enabled']) {
                $builder->getDefinition(DoctrineMailStore::class)->setArgument('$connection', $connection);
            }
        }
    }
}
