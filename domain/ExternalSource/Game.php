<?php

namespace SportsImport\ExternalSource;

use Sports\Competition;
use Sports\Competitor;
use Sports\Game as GameBase;

interface Game
{
    /**
     * @param Competition $competition
     * @return array|int[]
     */
    public function getBatchNrs(Competition $competition): array;
    /**
     * @param Competition $competition
     * @return array|GameBase[]
     */
    public function getGames(Competition $competition, int $batchNr): array;
    /**
     * @param Competition $competition
     * @param mixed $id
     * @return GameBase|null
     */
    // weet nog niet precies welke parameters
    // public function getGame( Competition $competition, $id ): ?GameBase;
}
