<?php

declare(strict_types=1);

namespace Acme;

use CODEHeures\Scrutineer\Model\Audience;
use CODEHeures\Scrutineer\Model\Scenario;
use CODEHeures\Scrutineer\Port\CatalogProvider;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * REQUIRED port. Declares the acceptance-test scenarios of the (fake) bookshop, filtered by the
 * current user's role: a scenario with `role: null` is playable by anyone, otherwise the role
 * must match. Scenarios are merely declared here in code — no table is imposed by the library.
 */
#[AsAlias(CatalogProvider::class)]
final class AcmeCatalogProvider implements CatalogProvider
{
    /** @return list<Scenario> */
    public function scenarios(?string $role): array
    {
        $all = [
            new Scenario(
                id: 'login',
                label: 'Se connecter',
                group: 'auth',
                entryPoint: '/login',
                steps: [
                    'Ouvrir /login et saisir les identifiants du membre de démonstration.',
                    'Valider.',
                ],
                expectedResult: 'Le membre arrive sur son tableau de bord.',
                audience: Audience::User,
                introducedIn: '1.0.0',
                lot: 'bookshop',
            ),
            new Scenario(
                id: 'search-catalog',
                label: 'Rechercher un livre dans le catalogue',
                group: 'catalog',
                role: 'member',
                entryPoint: '/catalog',
                steps: [
                    'Se connecter en tant que membre.',
                    'Rechercher « Sieste » dans le catalogue.',
                ],
                expectedResult: 'Au moins un résultat s\'affiche avec son ISBN et sa disponibilité.',
                audience: Audience::User,
                introducedIn: '1.0.0',
                lot: 'bookshop',
            ),
            new Scenario(
                id: 'borrow-book',
                label: 'Emprunter un livre disponible',
                group: 'loans',
                role: 'member',
                entryPoint: '/catalog',
                steps: [
                    'Se connecter en tant que membre.',
                    'Ouvrir un livre disponible et cliquer « Emprunter ».',
                ],
                expectedResult: 'Le livre passe « emprunté » et apparaît dans « Mes emprunts ». Réinitialiser ce scénario régénère un catalogue avec un livre disponible.',
                audience: Audience::User,
                description: 'Scénario mutant (crée un prêt) — rejouable via le reset.',
                introducedIn: '1.1.0',
                lot: 'bookshop',
            ),
            new Scenario(
                id: 'return-overdue-book',
                label: 'Enregistrer le retour d\'un livre en retard',
                group: 'loans',
                role: 'librarian',
                entryPoint: '/loans',
                steps: [
                    'Se connecter en tant que bibliothécaire.',
                    'Ouvrir le prêt en retard et enregistrer le retour.',
                ],
                expectedResult: 'Le retour est enregistré et la pénalité de retard est calculée. Le reset recrée un prêt dont l\'échéance est déjà dépassée.',
                audience: Audience::User,
                description: 'Décor TEMPOREL : l\'échéance est relative au reseed (toujours dans le passé).',
                introducedIn: '1.2.0',
                lot: 'bookshop',
            ),
        ];

        return array_values(array_filter(
            $all,
            static fn (Scenario $s): bool => null === $s->role || $s->role === $role,
        ));
    }
}
