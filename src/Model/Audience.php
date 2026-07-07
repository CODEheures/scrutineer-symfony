<?php

declare(strict_types=1);

namespace CODEHeures\Scrutineer\Model;

/** Who can run a scenario: an end user, or only the internal team. */
enum Audience: string
{
    case User = 'user';
    case Dev = 'dev';
}
