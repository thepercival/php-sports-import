<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsHelpers\Against\Side as AgainstSide;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Player as PlayerApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameLineups as AgainstGameLineupsData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameSidePlayers as AgainstGameSidePlayersData;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;
use stdClass;

class AgainstGameLineups extends ApiHelper
{
    public function __construct(
        protected PlayerApiHelper $playerApiHelper,
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    public function getPlayers(string|int $gameId, bool $resetCache): AgainstGameLineupsData
    {
        $cacheId = $this->getCacheId($gameId);
        if ($resetCache) {
            $this->resetDataFromCache($cacheId);
        }

        /** @var stdClass|null $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($gameId),
            $cacheId,
            $this->getCacheMinutes()
        );
        if ($apiData === null || !property_exists($apiData, 'home') || !property_exists($apiData, 'away')) {
            throw new \Exception('apidatarow should contain properties home and away', E_ERROR);
        }
        /** @var stdClass $homePlayers */
        $homePlayers = $apiData->home;
        $homeLineup = $this->convertApiPlayers(AgainstSide::Home, $homePlayers);
        /** @var stdClass $awayPlayers */
        $awayPlayers = $apiData->away;
        $awayLineup = $this->convertApiPlayers(AgainstSide::Away, $awayPlayers);
        return new AgainstGameLineupsData($homeLineup, $awayLineup);
    }

    protected function convertApiPlayers(AgainstSide $againstSide, stdClass $apiPlayers): AgainstGameSidePlayersData
    {
        if (!property_exists($apiPlayers, "players") || !is_array($apiPlayers->players)) {
            throw new \Exception('api-lineup does not contain properties home and away', E_ERROR);
        }
        /** @var list<stdClass> $playersApiData */
        $playersApiData = $apiPlayers->players;

        $players = array_map(function (stdClass $playerApiData): PlayerData {
            if (!property_exists($playerApiData, "player")) {
                throw new \Exception('player-apidata does not contain property player', E_ERROR);
            }
            /** @var stdClass $playerApiDataRow */
            $playerApiDataRow = $playerApiData->player;
            /** @var stdClass|null $statistics */
            $statistics = null;
            if (property_exists($playerApiData, "statistics")) {
                /** @var stdClass $statistics */
                $statistics = $playerApiData->statistics;
            }
            $playerData = $this->jsonToDataConverter->convertPlayerJsonToData($playerApiDataRow, $statistics);
            if ($playerData === null) {
                throw new \Exception('player should not be null', E_ERROR);
            }
            return $playerData;
        }, $playersApiData);

        return new AgainstGameSidePlayersData($againstSide, $players);
    }

    public function getCacheMinutes(): int
    {
        return 14; // @TODO ADD CACHE FOR DEV MODE
    }

    public function getCacheId(string|int $gameId): string
    {
        return $this->getEndPointSuffix($gameId);
    }

    public function getDefaultEndPoint(): string
    {
        return "event/**gameId**/lineups";
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
