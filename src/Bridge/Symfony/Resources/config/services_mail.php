<?php

declare(strict_types=1);

use CODEHeures\Scrutineer\Bridge\Doctrine\DoctrineMailStore;
use CODEHeures\Scrutineer\Bridge\Symfony\Mailer\CapturedMailListener;
use CODEHeures\Scrutineer\Console\ScrutineerMailbox;
use CODEHeures\Scrutineer\Port\MailStore;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Mail-capture services — imported by the bundle ONLY when `scrutineer.mail_capture.enabled`
 * is true, so a host that manages its own mail never gets the listener injected into its
 * mailer. Requires symfony/mailer + symfony/mime on the host (a `suggest`).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Default captured-mail store; the host may override the MailStore alias. Table is
    // config-driven; the connection autowires the default unless the bundle rebinds it.
    $services->set(DoctrineMailStore::class)
        ->arg('$table', '%scrutineer.mail_capture.table%');
    $services->alias(MailStore::class, DoctrineMailStore::class);

    $services->set(ScrutineerMailbox::class);

    // The chokepoint: strips X-Scrutineer-* from every mail and captures the reserved-domain
    // ones. Tagged as an event subscriber by autoconfigure (implements EventSubscriberInterface).
    $services->set(CapturedMailListener::class)
        ->arg('$domain', '%scrutineer.mail_capture.domain%');
};
