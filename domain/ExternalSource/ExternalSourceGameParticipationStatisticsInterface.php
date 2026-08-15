<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

use SportsImport\ExternalSource\Game\ParticipationStatistics;

/** @api */
interface ExternalSourceGameParticipationStatisticsInterface
{
    /**
     * @return list<ParticipationStatistics>
     */
    public function getAgainstGameParticipationStatistics(string|int $gameId): array;
}