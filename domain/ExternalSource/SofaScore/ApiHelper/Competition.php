<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use Sports\League;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource;
use SportsImport\ExternalSource\CacheInfo;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Competition as CompetitionData;
use stdClass;

class Competition extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

//    /**
//     * @param League $league
//     * @return list<CompetitionData>
//     */
//    public function getCompetitions(League $league): array
//    {
//        /** @var stdClass $apiData */
//        $apiData = $this->getData(
//            $this->getEndPoint($league),
//            $this->getCacheId($league),
//            $this->getCacheMinutes()
//        );
//        if (property_exists($apiData, "groups") === false) {
//            $this->logger->error('could not find stdClass-property "categories"');
//            return [];
//        }
//        $competitions = [];
//        {
//            /** @var list<stdClass> $groups */
//            $groups = $apiData->groups;
//
//            foreach ($groups as $group) {
//                if (property_exists($group, "uniqueTournaments") === false) {
//                    $this->logger->error('could not find stdClass-property "uniqueTournaments"');
//                    continue;
//                }
//                /** @var list<stdClass> $groupCompetitions */
//                $groupCompetitions = $group->uniqueTournaments;
//                foreach( $groupCompetitions as $groupCompetition) {
//                    $competitions[] = $this->convertApiData($groupCompetition);
//                }
//            }
//        }
//
//        return $competitions;
//    }

    /**
     * @param League $league
     * @return list<CompetitionData>
     */
    public function getCompetitions(League $league): array
    {
        /** @var stdClass $dateApiData */
        $dateApiData = $this->getData(
            $this->getEndPoint($league),
            $this->getCacheId($league),
            $this->getCacheMinutes()
        );
        if (property_exists($dateApiData, "seasons") === false) {
            $this->logger->error('could not find stdClass-property "seasons"');
            return [];
        }
        /** @var list<stdClass> $seasons */
        $seasons = $dateApiData->seasons;
        return array_map( function(stdClass $season): CompetitionData {
                                     return $this->convertApiDataRow($season);
                                 }, $seasons);
    }


    protected function convertApiDataRow(stdClass $apiDataRow): CompetitionData {
        return new CompetitionData(
            (string)$apiDataRow->id,
            (string)$apiDataRow->name,
            (string)$apiDataRow->year);
    }

    public function getCacheId(League $league): string {
        return $this->getEndPointSuffix($league);
    }

    public function getCacheMinutes(): int
    {
        return 60 * 24 * 30;
    }

    public function getDefaultEndPoint(): string
    {
        return "unique-tournament/**leagueId**/seasons";
    }

    public function getEndPoint(League $league): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getEndPointSuffix($league);
    }

    protected function getEndPointSuffix(League $league): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**leagueId**", (string)$league->getId(), $endpointSuffix);
    }

//    public function getCompetitionId(stdClass $externalCompetition): int|null
//    {
//        if (!property_exists($externalCompetition, "season")) {
//            $this->logger->error('could not find stdClass-property "season"');
//            return null;
//        }
//        /** @var stdClass $season */
//        $season = $externalCompetition->season;
//        if (!property_exists($season, "id")) {
//            $this->logger->error('could not find stdClass-property "id"');
//            return null;
//        }
//        return (int)$season->id;
//    }



//    /**
//     * @return array|DateTimeImmutable[]
//     */
//    protected function getCompetitionDates(): array
//    {
//        $firstSaturday = $this->getFirstSaturdayInEvenWeek();
//        return [
//            $firstSaturday,
//            $firstSaturday->modify("+28 days")
//        ];
//    }

//    /**
//     * @return array|DateTimeImmutable[]
//     */
//    protected function getCompetitionCacheDates(): array
//    {
//        $firstSaturday = $this->getFirstSaturdayInEvenWeek();
//        return [
//            $firstSaturday->modify("-28 days"),
//            $firstSaturday->modify("+56 days")
//        ];
//    }
//
//    protected function getFirstSaturdayInEvenWeek(): DateTimeImmutable
//    {
//        $today = (new DateTimeImmutable())->setTime(0, 0);
//
//        $delta = 0;
//        $weekNumber = (int)$today->format("W");
//        if (($weekNumber % 2) === 1) {
//            $delta = 7;
//        }
//        $dayCorrectWeek = $today->modify("+" . $delta . " days");
//
//        $dayOfWeek = (int)$dayCorrectWeek->format("w");
//
//        $deltaSaturDay = 6 - $dayOfWeek;
//
//        $firstCorrectSaturday = $dayCorrectWeek->modify("+" . $deltaSaturDay . " days");
//
//        return $firstCorrectSaturday;
//    }


}