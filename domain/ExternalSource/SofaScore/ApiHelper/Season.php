<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource;
use SportsImport\ExternalSource\CacheInfo;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Season as SeasonData;
use Sports\Sport;
use stdClass;

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
        $now = new \DateTimeImmutable();
        $thisYear2Digits = $now->format("Y");
        $nextYear2Digits = $now->modify("+1 years")->format("Y");
        $twoYears2Digits = $now->modify("+2 years")->format("Y");

        $thisSeason = new SeasonData($thisYear2Digits . "/" . $nextYear2Digits);
        $seasons[] = $thisSeason;

        $nextSeasonName = $nextYear2Digits . "/" . $twoYears2Digits;
        $nextSeason = new SeasonData($nextSeasonName);
        $seasons[] = $nextSeason;

        $thisYear4Digits = $now->format("Y");
        $nextYear4Digits = $now->modify("+1 years")->format("Y");

        $seasons[] = new SeasonData($thisYear4Digits);
        $seasons[] = new SeasonData($nextYear4Digits);

        return $seasons;
    }
}