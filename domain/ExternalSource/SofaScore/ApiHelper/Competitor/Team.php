<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper\Competitor;

use Psr\Log\LoggerInterface;
use Sports\Competition;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource;
use SportsImport\ExternalSource\CacheInfo;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Team as TeamApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Association as AssociationData;
use Sports\Sport;
use SportsImport\ExternalSource\SofaScore\Data\TeamCompetitor as TeamCompetitorData;
use stdClass;

class Team extends ApiHelper
{
    public function __construct(
        protected TeamApiHelper $teamApiHelper,
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param Competition $competition
     * @return list<TeamCompetitorData>
     */
    public function getTeamCompetitors(Competition $competition): array
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($competition),
            $this->getCacheId($competition),
            $this->getCacheMinutes()
        );
        return $this->convertApiToTeamCompetitorsData($apiData);
    }

    /**
     * @param stdClass $apiData
     * @return list<TeamCompetitorData>
     */
    protected function convertApiToTeamCompetitorsData(stdClass $apiData): array
    {
        if( !property_exists($apiData, 'standings') || !(is_array($apiData->standings) ) ) {
            throw new \Exception('the teamcompetitor-data is invalid', E_ERROR);
        }
        /** @var stdClass|false $standings */
        $standings = reset($apiData->standings);
        if( $standings === false)  {
            throw new \Exception('the teamcompetitor-data is invalid', E_ERROR);
        }
        $rows = $standings->rows;
        if( !is_array($rows) ) {
            throw new \Exception('the teamcompetitor-data is invalid', E_ERROR);
        }
        $teamCompetitors = [];
        foreach( $rows as $row ) {
            if( !($row instanceof stdClass) ) {
                continue;
            }
            $teamCompetitorData = $this->convertApiDataRow($row);
            if( $teamCompetitorData === null ) {
                $this->logger->error('could not convert api-data to teamcompetitor-data');
                continue;
            }
            $teamCompetitors[] = $teamCompetitorData;
        }
        uasort($teamCompetitors, function (TeamCompetitorData $a, TeamCompetitorData $b): int {
            return $a->id < $b->id ? -1 : 1;
        });
        if( count($teamCompetitors) === 0 ) {
            throw new \Exception('no teamcompetitor-data could be found', E_ERROR);
        }
        return array_values($teamCompetitors);
    }

    /**
     * @param stdClass $standingRow
     * @return TeamCompetitorData|null
     */
    protected function convertApiDataRow(stdClass $standingRow): TeamCompetitorData|null
    {
        if (!property_exists($standingRow, "team") || !($standingRow->team instanceof stdClass) ) {
            $this->logger->error('could not find stdClass-property "team"');
            return null;
        }
        $teamData = $this->teamApiHelper->convertApiDataRow($standingRow->team);
        if( $teamData === null ) {
            return null;
        }
        return new TeamCompetitorData((string)$standingRow->id, $teamData );
    }

    public function getCacheMinutes(): int
    {
        return 60 * 24 * 7;
    }

    public function getCacheId(Competition $competition): string
    {
        return $this->getEndPointSuffix($competition);
    }

    public function getDefaultEndPoint(): string
    {
        return "unique-tournament/**leagueId**/season/**competitionId**/standings/total";
    }

    public function getEndPoint(Competition $competition): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getEndPointSuffix($competition);
    }

    protected function getEndPointSuffix(Competition $competition): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        $retVal = str_replace("**leagueId**", (string)$competition->getLeague()->getId(), $endpointSuffix);
        return str_replace("**competitionId**", (string)$competition->getId(), $retVal);


    }
}