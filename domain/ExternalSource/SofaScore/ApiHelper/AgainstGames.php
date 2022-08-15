<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use Sports\Competition;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGame as AgainstGameApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGame as AgainstGameData;
use stdClass;

class AgainstGames extends ApiHelper
{
    public function __construct(
        protected AgainstGameApiHelper $againstGameDetailsApiHelper,
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @return list<AgainstGameData>
     */
    public function getAgainstGamesBasics(Competition $competition, int $gameRoundNumber): array
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($competition, $gameRoundNumber),
            $this->getCacheId($competition, $gameRoundNumber, true),
            $this->getCacheMinutes()
        );

        $againstGamesData = [];
        /** @var list<stdClass> $events */
        $events = $apiData->events;
        foreach ($events as $event) {
            $againstGameData = $this->convertBasicsApiDataRow($event);
            if ($againstGameData !== null) {
                $againstGamesData[] = $againstGameData;
            }
        }
        return $againstGamesData;
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @param bool $resetCache
     * @return list<AgainstGameData>
     */
    public function getAgainstGamesComplete(Competition $competition, int $gameRoundNumber, bool $resetCache): array
    {
        $cacheId = $this->getCacheId($competition, $gameRoundNumber, false);
        if ($resetCache) {
            $this->resetDataFromCache($cacheId);
        }

        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($competition, $gameRoundNumber),
            $cacheId,
            $this->getCacheMinutes()
        );

        $againstGamesData = [];
        /** @var list<stdClass> $events */
        $events = $apiData->events;
        foreach ($events as $event) {
            $againstGameData = $this->convertBasicsApiDataRow($event);
            if ($againstGameData !== null) {
                $this->againstGameDetailsApiHelper->finishAgainstGameData($againstGameData, $resetCache);
                $againstGamesData[] = $againstGameData;
            }
        }
        return $againstGamesData;
    }

//
    //if (array_key_exists($externalGame->event->id, $this->cache)) {
    //return $this->cache[$externalGame->event->id];
    //}
//
    //$startDateTime = new DateTimeImmutable("@" . $externalGame->event->startTimestamp . "");
//
    //if ($gameRoundNumber === null && property_exists($externalGame->event, "roundInfo")) {
//    $gameRoundNumber = $externalGame->event->roundInfo->round;
    //}
    //if (!is_int($gameRoundNumber)) {
//    return null;
    //}
    //$competitionSport = $competition->getSingleSport();
    //$game = new AgainstGame($poule, $gameRoundNumber, $startDateTime, $competitionSport, $gameRoundNumber);
    //if (property_exists($externalGame->event, "status")) {
//    $game->setState($this->apiHelper->convertState($externalGame->event->status->code));
    //}
    //$game->setId($externalGame->event->id);

    protected function convertBasicsApiDataRow(stdClass $apiDataRow): AgainstGameData|null
    {
        $againstGameData = $this->againstGameDetailsApiHelper->convertBasicsApiDataRow($apiDataRow);
        if ($againstGameData === null) {
            return null;
        }
        return $againstGameData;
    }

    public function getCacheMinutes(): int
    {
        return 60 * 23; // @TODO ADD CACHE FOR DEV MODE
    }

    public function getCacheId(Competition $competition, int $gameRoundNumber, bool $onlyScheduled): string
    {
        return $this->getEndPointSuffix($competition, $gameRoundNumber) . (!$onlyScheduled ? '-scheduled' : '');
    }

    public function getDefaultEndPoint(): string
    {
        return "unique-tournament/**leagueId**/season/**competitionId**/events/round/**gameRoundNumber**";
    }

    public function getEndPoint(Competition $competition, int $gameRoundNumber): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getEndPointSuffix($competition, $gameRoundNumber);
    }

    protected function getEndPointSuffix(Competition $competition, int $gameRoundNumber): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        $retVal = str_replace("**leagueId**", (string)$competition->getLeague()->getId(), $endpointSuffix);
        $retVal = str_replace("**competitionId**", (string)$competition->getId(), $retVal);
        return str_replace("**gameRoundNumber**", (string)$gameRoundNumber, $retVal);
    }
}
