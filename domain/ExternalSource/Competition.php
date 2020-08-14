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
     * @param mixed $id
     * @return CompetitionBase|null
     */
    public function getCompetition($id): ?CompetitionBase;
}
