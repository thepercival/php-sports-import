<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelpers;

use Psr\Log\LoggerInterface;
use SportsHelpers\Against\AgainstSide;
use SportsImport\CacheItemDb\CacheItemDbRepositoryInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameLineups as AgainstGameLineupsData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameMissingPlayer as AgainstGameMissingPlayerData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameSidePlayers as AgainstGameSidePlayersData;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;
use stdClass;

final class AgainstGameLineupsApiHelper extends ApiHelperAbstract
{
    public function __construct(
        //        protected PlayerApiHelper $playerApiHelper,
        SofaScore $sofaScore,
        CacheItemDbRepositoryInterface $cacheItemDbRepos,
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
        $missingPlayers = array_merge(
            $this->convertApiMissingPlayers(AgainstSide::Home, $homePlayers),
            $this->convertApiMissingPlayers(AgainstSide::Away, $awayPlayers)
        );
        return new AgainstGameLineupsData($homeLineup, $awayLineup, $missingPlayers);
    }

    /**
     * @return list<AgainstGameMissingPlayerData>
     */
    protected function convertApiMissingPlayers(AgainstSide $againstSide, stdClass $apiPlayers): array
    {
        if (!property_exists($apiPlayers, 'missingPlayers')) {
            return [];
        }
        if (!is_array($apiPlayers->missingPlayers)) {
            throw new \Exception('api-lineup property missingPlayers should be an array', E_ERROR);
        }

        $missingPlayers = [];
        foreach ($apiPlayers->missingPlayers as $missingPlayerApiData) {
            if (!($missingPlayerApiData instanceof stdClass)) {
                throw new \Exception('missing-player data should be an object', E_ERROR);
            }
            foreach (['player', 'type', 'reason', 'description', 'externalType'] as $property) {
                if (!property_exists($missingPlayerApiData, $property)) {
                    throw new \Exception('missing-player data does not contain property ' . $property, E_ERROR);
                }
            }
            if (!($missingPlayerApiData->player instanceof stdClass)) {
                throw new \Exception('missing-player property player should be an object', E_ERROR);
            }
            $player = $this->jsonToDataConverter->convertPlayerJsonToData($missingPlayerApiData->player, null);
            if ($player === null) {
                continue;
            }
            $expectedEndDate = null;
            if (property_exists($missingPlayerApiData, 'expectedEndDate')) {
                $expectedEndDate = new \DateTimeImmutable((string)$missingPlayerApiData->expectedEndDate);
            }
            $missingPlayers[] = new AgainstGameMissingPlayerData(
                $player,
                $againstSide,
                (string)$missingPlayerApiData->type,
                (int)$missingPlayerApiData->reason,
                (string)$missingPlayerApiData->description,
                (int)$missingPlayerApiData->externalType,
                $expectedEndDate
            );
        }
        return $missingPlayers;
    }

    protected function convertApiPlayers(AgainstSide $againstSide, stdClass $apiPlayers): AgainstGameSidePlayersData
    {
        if (!property_exists($apiPlayers, "players") || !is_array($apiPlayers->players)) {
            throw new \Exception('api-lineup does not contain properties home and away', E_ERROR);
        }
        /** @var list<stdClass> $playersApiData */
        $playersApiData = $apiPlayers->players;

        $players = array_map(function (stdClass $playerApiData): PlayerData|null {
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
            return $this->jsonToDataConverter->convertPlayerJsonToData($playerApiDataRow, $statistics);
        }, $playersApiData);

        $players = array_values(array_filter($players, function (PlayerData|null $playerApiData): bool {
            return $playerApiData !== null;
        }));

        return new AgainstGameSidePlayersData($againstSide, $players);
    }

    #[\Override]
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
