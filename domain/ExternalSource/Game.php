<?php

namespace Voetbal\ExternalSource;

use Voetbal\Competition;
use Voetbal\Competitor as CompetitorBase;
use Voetbal\Game as GameBase;

interface Game
{
    /**
     * @param Competition $competition
     * @param bool $forImport
     * @return array|int[]
     */
    public function getBatchNrs(Competition $competition, bool $forImport): array;
    /**
     * @param Competition $competition
     * @return array
     */
    public function getGames(Competition $competition, int $batchNr): array;
    /**
     * @param Competition $competition
     * @return array
     */
    // public function getGamesByPeriod( Competition $competition, Period $period ): array;
    /**
     * @param Competition $competition
     * @param mixed $id
     * @return GameBase|null
     */
    // weet nog niet precies welke parameters
    // public function getGame( Competition $competition, $id ): ?GameBase;
}
