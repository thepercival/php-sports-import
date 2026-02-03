<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Season as SeasonData;
use SportsImport\Repositories\CacheItemDbRepository as CacheItemDbRepository;

final class Season extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    #[\Override]
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
        $twoYearsInFuture = (new \DateTimeImmutable())->add(new \DateInterval('P2Y'))->format("Y");
        for ($year = 2014; $year <= $twoYearsInFuture; $year++) {
            $seasons[] = new SeasonData($year . '/' . ($year + 1));
            $seasons[] = new SeasonData((string)$year);
        }
        return $seasons;
    }
}
