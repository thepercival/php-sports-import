<?php

namespace SportsImport\ExternalSource\Competitor;

use Sports\Competitor\Team as TeamCompetitor;
use Sports\Competition;

interface Team
{
    /**
     * @return list<TeamCompetitor>
     */
    public function getTeamCompetitors(Competition $competition): array;
    public function getTeamCompetitor(Competition $competition, string|int $id): TeamCompetitor|null;
}
