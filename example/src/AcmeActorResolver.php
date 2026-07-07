<?php

declare(strict_types=1);

namespace Acme;

use CODEHeures\Scrutineer\Port\ActorResolver;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * OPTIONAL port. Turns opaque actorRefs into human labels AT RENDER TIME, so the history ledger
 * stays PII-free. An unknown ref simply falls back to the raw ref (handled by the library).
 */
#[AsAlias(ActorResolver::class)]
final class AcmeActorResolver implements ActorResolver
{
    private const DIRECTORY = [
        'member-42' => 'Ada Member',
        'librarian-7' => 'Grace Librarian',
    ];

    /**
     * @param  list<string>          $actorRefs
     * @return array<string, string>            map of actorRef => display label
     */
    public function resolve(array $actorRefs): array
    {
        $labels = [];
        foreach ($actorRefs as $ref) {
            if (isset(self::DIRECTORY[$ref])) {
                $labels[$ref] = self::DIRECTORY[$ref];
            }
        }

        return $labels;
    }
}
