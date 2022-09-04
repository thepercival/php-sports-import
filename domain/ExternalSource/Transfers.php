<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Competition;
use Sports\Team;
use SportsImport\Transfer;

interface Transfers
{
    /**
     * @param Competition $competition
     * @param Team $team
     * @return list<Transfer>
     */
    public function getTransfers(Competition $competition, Team $team): array;
}

