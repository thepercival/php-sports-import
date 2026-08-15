<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

/** @api */
final readonly class GameParticipationStatisticsCategory
{
    /**
     * @param list<GameParticipationStatistic> $statistics
     */
    public function __construct(
        public string $name,
        public array $statistics
    ) {
    }
}