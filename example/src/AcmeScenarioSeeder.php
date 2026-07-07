<?php

declare(strict_types=1);

namespace Acme;

use CODEHeures\Scrutineer\Model\ScrutineerContext;
use CODEHeures\Scrutineer\Port\ScenarioSeeder;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * REQUIRED port. (Re)builds and tears down the decor a scenario needs, on YOUR domain — here the
 * in-memory {@see Bookshop}. On a reset the library calls purge() then seed(); it never knows
 * what a "book" is. Isolation is the host's call: this example keeps a single shared bookshop,
 * but you could key the decor by $context->actorRef / $context->scopeKey for per-actor isolation.
 */
#[AsAlias(ScenarioSeeder::class)]
final class AcmeScenarioSeeder implements ScenarioSeeder
{
    public function __construct(private readonly Bookshop $bookshop)
    {
    }

    public function seed(string $scenarioId, ScrutineerContext $context): void
    {
        $this->bookshop->reset();

        match ($scenarioId) {
            'search-catalog' => $this->seedCatalog(),
            'borrow-book' => $this->bookshop->addBook('978-2-1234-5680-3', 'Petit Précis de Sieste', available: true),
            'return-overdue-book' => $this->seedOverdueLoan($context->actorRef),
            default => null, // 'login' and unknown ids need no decor
        };
    }

    public function purge(string $scenarioId, ScrutineerContext $context): void
    {
        // Tearing down = wiping the decor; the next seed() rebuilds a clean baseline.
        $this->bookshop->reset();
    }

    private function seedCatalog(): void
    {
        $this->bookshop->addBook('978-2-1234-5680-3', 'Petit Précis de Sieste', available: true);
        $this->bookshop->addBook('978-2-1234-5681-0', 'Cuisine Végétale', available: true);
        $this->bookshop->addBook('978-2-1234-5682-7', 'Histoire des Phares', available: false);
    }

    private function seedOverdueLoan(string $member): void
    {
        $this->bookshop->addBook('978-2-1234-5682-7', 'Histoire des Phares', available: false);
        // Temporal decor: the due date is ALWAYS in the past, relative to the reseed.
        $this->bookshop->lend('978-2-1234-5682-7', $member, new \DateTimeImmutable('-3 days'));
    }
}
