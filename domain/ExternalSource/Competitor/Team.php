<?php

namespace SportsImport\ExternalSource\Competitor;

use Sports\Competitor\Team as TeamCompetitor;
use Sports\Competition;

interface Team
{
    /**
     * @return array|TeamCompetitor[]
     */
    public function getTeamCompetitors(Competition $competition): array;
    /**
     * @param mixed $id
     * @return TeamCompetitor|null
     */
    public function getTeamCompetitor(Competition $competition, $id): ?TeamCompetitor;
}
