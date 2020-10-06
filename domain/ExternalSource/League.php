<?php

namespace SportsImport\ExternalSource;

use Sports\League as LeagueBase;
use Sports\Association;

interface League
{
    /**
     * @param Association $association
     * @return array|LeagueBase[]
     */
    public function getLeagues( Association $association): array;

    /**
     * @param Association $association
     * @param mixed $id
     * @return LeagueBase|null
     */
    public function getLeague(Association $association, $id): ?LeagueBase;
}
