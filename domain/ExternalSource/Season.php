<?php

namespace SportsImport\ExternalSource;

use Sports\Season as SeasonBase;

interface Season
{
    /**
     * @return array|SeasonBase[]
     */
    public function getSeasons(): array;
    /**
     * @param string|int $id
     * @return SeasonBase|null
     */
    public function getSeason($id): ?SeasonBase;
}
