<?php

namespace SportsImport\ExternalSource;

use Sports\Competition as CompetitionBase;
use Sports\League;
use Sports\Season;
use Sports\Sport;

interface Competition
{
    /**
     * @param Sport $sport,
     * @param League $league
     * @return array<int|string, CompetitionBase>
     */
    public function getCompetitions( Sport $sport, League $league): array;

    /**
     * @param Sport $sport,
     * @param League $league
     * @param Season $season
     * @return CompetitionBase|null
     */
    public function getCompetition( Sport $sport, League $league, Season $season): ?CompetitionBase;
}
