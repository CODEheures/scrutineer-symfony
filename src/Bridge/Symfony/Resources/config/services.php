<?php

declare(strict_types=1);

use CODEHeures\Scrutineer\Bridge\Doctrine\DoctrineHistoryStore;
use CODEHeures\Scrutineer\Bridge\Symfony\Command\ExportHistoryCommand;
use CODEHeures\Scrutineer\Bridge\Symfony\Command\GenerateMigrationCommand;
use CODEHeures\Scrutineer\Bridge\Symfony\Controller\ScrutineerController;
use CODEHeures\Scrutineer\Console\ScrutineerConsole;
use CODEHeures\Scrutineer\Console\ScrutineerGuard;
use CODEHeures\Scrutineer\Port\ActorResolver;
use CODEHeures\Scrutineer\Port\HistoryStore;
use CODEHeures\Scrutineer\Port\TicketPublisher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Default ledger store; the host may override the HistoryStore alias. The table name is
    // config-driven (%scrutineer.table%); the connection is autowired to the default unless
    // the bundle rebinds it from the `connection` config key.
    $services->set(DoctrineHistoryStore::class)
        ->arg('$table', '%scrutineer.table%');
    $services->alias(HistoryStore::class, DoctrineHistoryStore::class);

    // The ActorResolver and TicketPublisher ports are optional — bound only if the host
    // registered one (else null → actor refs stay raw / publishing answers "unsupported").
    $services->set(ScrutineerConsole::class)
        ->arg('$actorResolver', service(ActorResolver::class)->ignoreOnInvalid())
        ->arg('$ticketPublisher', service(TicketPublisher::class)->ignoreOnInvalid());

    $services->set(ScrutineerGuard::class)
        ->arg('$enabled', '%scrutineer.enabled%');

    $services->set(ScrutineerController::class)
        ->arg('$assetPath', '%scrutineer.asset_path%')
        ->tag('controller.service_arguments');

    // CSV export of the history (reporting tier ①). Autoconfigured as a console command.
    $services->set(ExportHistoryCommand::class)
        ->arg('$actors', service(ActorResolver::class)->ignoreOnInvalid())
        ->arg('$defaultLang', '%scrutineer.language%');

    // Generates the ledger migration into the host's own migrations path. The DependencyFactory
    // is bound only if doctrine/doctrine-migrations-bundle is installed (a `suggest`); absent,
    // the command reports the missing dependency instead of failing to wire.
    $services->set(GenerateMigrationCommand::class)
        ->arg('$table', '%scrutineer.table%')
        ->arg('$dependencyFactory', service('doctrine.migrations.dependency_factory')->ignoreOnInvalid());
};
