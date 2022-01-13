<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Season as SeasonData;

class Season extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    public function getCacheMinutes(): int
    {
        return 0;
    }

    /**
     * @return list<SeasonData>
     */
    public function getSeasons(): array
    {
        $seasons = [];
        $twoYearsInFuture = (new \DateTimeImmutable())->modify("+2 years")->format("Y");
        for ($year = 2014; $year <= $twoYearsInFuture; $year++) {
            $seasons[] = new SeasonData($year . '/' . ($year + 1));
            $seasons[] = new SeasonData((string)$year);
        }
        return $seasons;
    }
}
