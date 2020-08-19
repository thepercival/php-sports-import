<?php

namespace SportsImport\ExternalSource;

use Sports\Competition as CompetitionBase;

interface Competition
{
    /**
     * @return array|CompetitionBase[]
     */
    public function getCompetitions(): array;
    /**
     * @param int|string $leagueId
     * @param int|string $seasonId
     * @return CompetitionBase|null
     */
    public function getCompetition($leagueId, $seasonId): ?CompetitionBase;
}
