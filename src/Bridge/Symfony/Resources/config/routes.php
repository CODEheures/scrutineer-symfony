<?php

declare(strict_types=1);

use CODEHeures\Scrutineer\Bridge\Symfony\Controller\ScrutineerController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Routes are shipped WITHOUT a prefix; the host imports this file under its configured
 * mount (SCRUTINEER_ROUTE_PREFIX). All routes are additionally gated at runtime by
 * ScrutineerGuard (the SCRUTINEER_ENABLED env-var). Authentication of the mutating routes
 * (/reset, /events, /history) is the host's concern — see the host's security config.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('scrutineer_catalog', '/catalog')
        ->controller([ScrutineerController::class, 'catalog'])
        ->methods(['GET']);

    // Pre-flight gate: NOT behind the guard — it answers {available:false} when off, so the
    // console can refuse to open even with the local flag forced (host security keeps it public).
    $routes->add('scrutineer_availability', '/availability')
        ->controller([ScrutineerController::class, 'availability'])
        ->methods(['GET']);

    $routes->add('scrutineer_publish', '/publish')
        ->controller([ScrutineerController::class, 'publish'])
        ->methods(['POST']);

    $routes->add('scrutineer_reset', '/reset')
        ->controller([ScrutineerController::class, 'reset'])
        ->methods(['POST']);

    $routes->add('scrutineer_history', '/history')
        ->controller([ScrutineerController::class, 'history'])
        ->methods(['GET']);

    $routes->add('scrutineer_events', '/events')
        ->controller([ScrutineerController::class, 'events'])
        ->methods(['POST']);

    $routes->add('scrutineer_asset', '/console.js')
        ->controller([ScrutineerController::class, 'asset'])
        ->methods(['GET']);

    // Locale add-ons sit beside the main asset: GET <prefix>/console.<lang>.js.
    $routes->add('scrutineer_asset_locale', '/console.{lang}.js')
        ->controller([ScrutineerController::class, 'asset'])
        ->requirements(['lang' => '[a-z]{2}'])
        ->methods(['GET']);
};
