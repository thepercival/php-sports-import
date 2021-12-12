<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use Sports\Competition;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use stdClass;

class GameRoundNumbers extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param Competition $competition
     * @return list<int>
     */
    public function getGameRoundNumbers(Competition $competition): array
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($competition),
            $this->getCacheId($competition),
            $this->getCacheMinutes()
        );

        // currentRound stdClass prop round(int)
        if (!property_exists($apiData, "rounds")) {
            $this->logger->error('could not find stdClass-property "rounds"');
            return [];
        }
        $gameRoundsData = $apiData->rounds;
        if (!is_array($gameRoundsData)) {
            return [];
        }
        $gameRoundsDataRetVal = [];
        /** @var stdClass $gameRoundData */
        foreach ($gameRoundsData as $gameRoundData) {
            if (!property_exists($gameRoundData, "round")) {
                $this->logger->error('could not find stdClass-property "round"');
                continue;
            }
            $gameRoundsDataRetVal[] = (int)$gameRoundData->round;
        }
        return $gameRoundsDataRetVal;
    }

    public function getCacheId(Competition $competition): string
    {
        return $this->getEndPointSuffix($competition);
    }

    public function getCacheMinutes(): int
    {
        return 60 * 24 * 7;
    }

    public function getDefaultEndPoint(): string
    {
        return "unique-tournament/**leagueId**/season/**competitionId**/rounds";
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
