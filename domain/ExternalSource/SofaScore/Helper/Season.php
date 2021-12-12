<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use League\Period\Period;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\Data\Season as SeasonData;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Season as SeasonApiHelper;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Sports\Season as SeasonBase;

/**
 * @template-extends SofaScoreHelper<SeasonBase>
 */
class Season extends SofaScoreHelper
{
    public function __construct(
        protected SeasonApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @return array<int|string, SeasonBase>
     */
    public function getSeasons(): array
    {
        $seasons = [];
        $seasonsData = $this->apiHelper->getSeasons();

        foreach ($seasonsData as $seasonData) {
            $seasons[$seasonData->name] = $this->convertDataToSeason($seasonData);
        }
        return $seasons;
    }

    public function getSeason(string|int $id): SeasonBase|null
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }
        $seasons = $this->getSeasons();
        if (array_key_exists($id, $seasons)) {
            return $seasons[$id];
        }
        return null;
    }

    protected function convertDataToSeason(SeasonData $seasonData): SeasonBase
    {
        $name = $this->apiHelper->convertToSeasonId($seasonData->name);
        if (array_key_exists($name, $this->cache)) {
            return $this->cache[$name];
        }
        $season = new SeasonBase($name, $this->getPeriod($name));
        $season->setId($name);
        $this->cache[$name] = $season;
        return $season;
    }

    protected function getPeriod(string $name): Period
    {
        if (strpos($name, "/") !== false) {
            $year = substr($name, 0, 4);
            $start = $year . "-07-01";
            $year = substr($name, 5, 4);
            $end = $year . "-07-01";
        } else {
            $start = $name . "-01-01";
            $end = (((int)$name)+1) . "-01-01";
        }
        $startDateTime = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:s", $start . "T00:00:00", new \DateTimeZone('UTC'));
        $endDateTime = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:s", $end . "T00:00:00", new \DateTimeZone('UTC'));
        return new Period($startDateTime, $endDateTime);
    }
}
