<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Season as SeasonBase;

interface Season
{
    /**
     * @return array<int|string, SeasonBase>
     */
    public function getSeasons(): array;
    public function getSeason(string|int $id): SeasonBase|null;
}
