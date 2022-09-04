<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Sports\Game\State as GameState;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameEvents as EventsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameLineups as LineupsApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGame as AgainstGameData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameRound as AgainstGameRoundData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameScore as AgainstGameScoreData;
use stdClass;

class AgainstGame extends ApiHelper
{
    public function __construct(
        protected LineupsApiHelper $lineupApiHelper,
        protected EventsApiHelper $eventApiHelper,
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    public function getAgainstGame(string|int $gameId, bool $resetCache): AgainstGameData|null
    {
        $apiDataRow = $this->getAgainstGameBasics($gameId, $resetCache);

        if ($apiDataRow === null) {
            return null;
        }
        $againstGameData = $this->convertBasicsApiDataRow($apiDataRow);
        if ($againstGameData !== null) {
            $this->finishAgainstGameData($againstGameData, $resetCache);
        }
        return $againstGameData;
    }

    public function finishAgainstGameData(AgainstGameData $againstGameData, bool $resetCache): void
    {
        if ($againstGameData->state === GameState::Finished) {
            $againstGameData->players = $this->lineupApiHelper->getPlayers($againstGameData->id, $resetCache);
            $againstGameData->events = $this->eventApiHelper->getEvents($againstGameData->id, $resetCache);
        }
    }

    public function getAgainstGameBasics(string|int $gameId, bool $resetCache): stdClass|null
    {
        $cacheId = $this->getCacheId($gameId, true);
        if ($resetCache) {
            $this->resetDataFromCache($cacheId);
        }

        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($gameId),
            $cacheId,
            $this->getCacheMinutes()
        );
        return $apiData;
    }

    public function convertBasicsApiDataRow(stdClass $apiDataRow): AgainstGameData|null
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
        $homeTeamData = $this->jsonToDataConverter->convertTeamJsonToData($homeTeamApiData);
        /** @var stdClass $awayTeamApiData */
        $awayTeamApiData = $apiDataRow->awayTeam;
        $awayTeamData = $this->jsonToDataConverter->convertTeamJsonToData($awayTeamApiData);
        if ($homeTeamData === null || $awayTeamData === null) {
            throw new \Exception('home- or awayteam could not be found', E_ERROR);
        }
        /** @var stdClass $status */
        $status = $apiDataRow->status;
        $state = $this->convertState((int)$status->code);

        $home = 0;
        $away = 0;
        /** @psalm-suppress RedundantCondition */
        if ($state === GameState::Finished && is_object($apiDataRow->homeScore) && is_object($apiDataRow->awayScore)
            && property_exists($apiDataRow->homeScore, "current")
            && property_exists($apiDataRow->awayScore, "current")) {
            $home = (int)$apiDataRow->homeScore->current;
            $away = (int)$apiDataRow->awayScore->current;
        }
        $againstGameData = new AgainstGameData(
            (string)$apiDataRow->id,
            $start,
            new AgainstGameRoundData($gameRoundNumber),
            $state,
            $homeTeamData,
            $awayTeamData,
            new AgainstGameScoreData($home),
            new AgainstGameScoreData($away)
        );
        return $againstGameData;
    }

    public function getCacheInfo(string|int $gameId, bool $onlyScheduled): string
    {
        return $this->getCacheInfoHelper($this->getCacheId($gameId, $onlyScheduled));
    }

    public function getCacheMinutes(): int
    {
        return 14; // @TODO ADD CACHE FOR DEV MODE
    }

    public function getCacheId(string|int $gameId, bool $onlyScheduled): string
    {
        return $this->getEndPointSuffix($gameId) . (!$onlyScheduled ? '-scheduled' : '');
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
