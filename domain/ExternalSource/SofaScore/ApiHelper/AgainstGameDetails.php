<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Sports\State;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameLineups as LineupsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameEvents as EventsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Team as TeamApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGame as AgainstGameData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameRound as AgainstGameRoundData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameScore as AgainstGameScoreData;
use stdClass;

class AgainstGameDetails extends ApiHelper
{
    public function __construct(
        protected LineupsApiHelper $lineupApiHelper,
        protected EventsApiHelper $eventApiHelper,
        protected TeamApiHelper $teamApiHelper,
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param string|int $gameId
     * @return AgainstGameData
     */
    public function getAgainstGame(string|int $gameId): AgainstGameData|null
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($gameId),
            $this->getCacheId($gameId),
            $this->getCacheMinutes()
        );

        return $this->convertApiDataRow($apiData);
    }

    public function convertApiDataRow(stdClass $apiDataRow): AgainstGameData|null
    {
        if (property_exists($apiDataRow, "event")) {
            /** @var stdClass $apiDataRow */
            $apiDataRow = $apiDataRow->event;
        }

        $start = new DateTimeImmutable("@" . (string)$apiDataRow->startTimestamp . "");

        if (!property_exists($apiDataRow, "roundInfo")) {
            throw new \Exception('roundInfo could not be found', E_ERROR);
        }
        /** @var stdClass $roundInfoApiData */
        $roundInfoApiData = $apiDataRow->roundInfo;
        $gameRoundNumber = $roundInfoApiData->round;
        if (!is_int($gameRoundNumber)) {
            return null;
        }

        if (!property_exists($apiDataRow, "status")) {
            throw new \Exception('status could not be found', E_ERROR);
        }
        /** @var stdClass $homeTeamApiData */
        $homeTeamApiData = $apiDataRow->homeTeam;
        $homeTeamData = $this->teamApiHelper->convertApiDataRow($homeTeamApiData);
        /** @var stdClass $awayTeamApiData */
        $awayTeamApiData = $apiDataRow->awayTeam;
        $awayTeamData = $this->teamApiHelper->convertApiDataRow($awayTeamApiData);
        if ($homeTeamData === null || $awayTeamData === null) {
            throw new \Exception('home- or awayteam could not be found', E_ERROR);
        }
        /** @var stdClass $status */
        $status = $apiDataRow->status;
        $state = $this->convertState((int)$status->code);

        $home = 0; $away = 0;
        /** @psalm-suppress RedundantCondition */
        if ($state === State::Finished and is_object($apiDataRow->homeScore) and is_object($apiDataRow->awayScore)) {
            $home = (int)$apiDataRow->homeScore->current;
            $away = (int)$apiDataRow->awayScore->current;
        }
        $againstGameData =  new AgainstGameData(
            (string)$apiDataRow->id,
            $start,
            new AgainstGameRoundData($gameRoundNumber),
            $state,
            $homeTeamData, $awayTeamData,
            new AgainstGameScoreData($home), new AgainstGameScoreData($away)
        );

        if( $state === State::Finished) {
            $againstGameData->lineups = $this->lineupApiHelper->getLineups($againstGameData->id);
            $againstGameData->events = $this->eventApiHelper->getEvents($againstGameData->id);
        }
        return $againstGameData;
    }

    public function getCacheInfo(string|int $gameId): string {
        return $this->getCacheInfoHelper($this->getCacheId($gameId));
    }

    public function getCacheMinutes(): int
    {
        return 1555000; // @TODO CDK 55
    }

    public function getCacheId(string|int $gameId): string
    {
        return $this->getEndPointSuffix($gameId);
    }

    public function getDefaultEndPoint(): string
    {
        return "event/**gameId**";
    }

    public function getEndPoint(string|int $gameId): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getEndPointSuffix($gameId);
    }

    protected function getEndPointSuffix(string|int $gameId): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**gameId**", (string)$gameId, $endpointSuffix);
    }
}