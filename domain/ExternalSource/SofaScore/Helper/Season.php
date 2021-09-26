<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use League\Period\Period;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Sports\Season as SeasonBase;
use SportsImport\ExternalSource\Season as ExternalSourceSeason;

/**
 * @template-extends SofaScoreHelper<SeasonBase>
 */
class Season extends SofaScoreHelper implements ExternalSourceSeason
{
    /**
     * @return array<int|string, SeasonBase>
     */
    public function getSeasons(): array
    {
        $seasons = [];
        $externalSeasons = $this->apiHelper->getSeasonsData();

        foreach ($externalSeasons as $externalSeason) {
            $season = $this->convertToSeason($externalSeason);
            $seasonId = $season->getId();
            if ($seasonId === null) {
                continue;
            }
            $seasons[$seasonId] = $season;
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

    protected function convertToSeason(stdClass $externalSeason): SeasonBase
    {
        /** @var string $externalSeasonName */
        $externalSeasonName = $externalSeason->name;
        $name = $this->apiHelper->convertToSeasonId($externalSeasonName);
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
