<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsHelpers\Against\Side as AgainstSide;
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

    public function getLineups(string|int $gameId): AgainstGameLineupsData
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($gameId),
            $this->getCacheId($gameId),
            $this->getCacheMinutes()
        );
        if (!property_exists($apiData, "home") || !property_exists($apiData, "away")) {
            throw new \Exception('apidatarow should contain properties home and away', E_ERROR);
        }
        /** @var stdClass $homePlayers */
        $homePlayers = $apiData->home;
        $homeLineup = $this->convertApiLineup( AgainstSide::Home, $homePlayers );
        /** @var stdClass $awayPlayers */
        $awayPlayers = $apiData->away;
        $awayLineup = $this->convertApiLineup( AgainstSide::Away, $awayPlayers );
        return new AgainstGameLineupsData($homeLineup, $awayLineup);
    }

    protected function convertApiLineup(AgainstSide $againstSide, stdClass $apiLineup): AgainstGameSidePlayersData
    {
        if (!property_exists($apiLineup, "players") || !is_array($apiLineup->players)) {
            throw new \Exception('api-lineup does not contain properties home and away', E_ERROR);
        }
        /** @var list<stdClass> $playersApiData */
        $playersApiData = $apiLineup->players;

        // remove players which will not appear
        $appearedPlayersApiData = array_filter($playersApiData, function (stdClass $playerApiData): bool {
            if (!property_exists($playerApiData, "statistics")) {
                return false;
            }
            /** @var stdClass $statistics */
            $statistics = $playerApiData->statistics;
            return property_exists($statistics, "minutesPlayed");
        });

        $appearedPlayersApiData = array_values(
            array_map(function (stdClass $playerApiData): PlayerData {
                if (!property_exists($playerApiData, "player")) {
                    throw new \Exception('player-apidata does not contain property player', E_ERROR);
                }
                /** @var stdClass $playerApiDataRow */
                $playerApiDataRow = $playerApiData->player;
                $playerData = $this->playerApiHelper->convertApiDataRow($playerApiDataRow);
                if ($playerData === null) {
                    throw new \Exception('player should not be null', E_ERROR);
                }
                return $playerData;
            }, $appearedPlayersApiData)
        );

        return new AgainstGameSidePlayersData($againstSide, $appearedPlayersApiData);
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