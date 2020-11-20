<?php

namespace SportsImport\ExternalSource;

use Sports\Team as TeamBase;
use Sports\Competition;

interface Team
{
    /**
     * @param Competition $competition
     * @return array|TeamBase[]
     */
    public function getTeams(Competition $competition): array;
    /**
     * @param Competition $competition
     * @param string|int $id
     * @return TeamBase|null
     */
    public function getTeam(Competition $competition, $id): ?TeamBase;
    public function getImageTeam( string $teamExternalId ): string;
}
