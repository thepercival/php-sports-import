<?php

namespace SportsImport\ExternalSource;

use Sports\League as LeagueBase;
use Sports\Association;

interface League
{
    /**
     * @param Association $association
     * @return array<int|string, LeagueBase>
     */
    public function getLeagues(Association $association): array;
    public function getLeague(Association $association, string|int $id): LeagueBase|null;
}
