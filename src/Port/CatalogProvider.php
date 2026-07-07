<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Port;

use CODEHeures\Scrutineer\Model\Scenario;

/**
 * Host-declared catalogue of the acceptance-test scenarios.
 *
 * A SCENARIO is one atomic acceptance test — a thing a tester does end-to-end and judges pass
 * or fail ({@see Scenario} carries its label, steps, expected result, required role and the
 * "lot" of decor it needs). They are NOT a library-imposed table: their content is your domain,
 * so you declare them however you want (a PHP file, a config, your own database). The library
 * only reads the resulting descriptors.
 */
interface CatalogProvider
{
    /**
     * The scenarios the CURRENT TESTER is allowed to see, given their role.
     *
     * @param string|null $role The current tester's authorisation role in YOUR application — the
     *                          value returned by {@see ScrutineerContextProvider::role()} (e.g.
     *                          "admin", "manager", "member"…), or null when the tester has no
     *                          role or you do not gate scenarios by role. Return only the
     *                          scenarios that role may play: typically the ones whose
     *                          {@see Scenario::$role} is null (playable by anyone) OR equals
     *                          $role. Do the filtering HERE — the library does not re-check it.
     *
     * @return list<Scenario>
     */
    public function scenarios(?string $role): array;
}
