<?php

namespace Voetbal\ExternalSource;

use Voetbal\Competitor as CompetitorBase;
use Voetbal\Competition;

interface Competitor
{
    /**
     * @param Competition $competition
     * @return array
     */
    public function getCompetitors(Competition $competition): array;
    /**
     * @param Competition $competition
     * @param mixed $id
     * @return CompetitorBase|null
     */
    public function getCompetitor(Competition $competition, $id): ?CompetitorBase;
}
