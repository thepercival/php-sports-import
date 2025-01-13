<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use Sports\Sport;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Player as PlayerApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent as AgainstGameEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Card as CardEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Goal as GoalEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Substitution as SubstitutionEventData;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;
use stdClass;

class AgainstGameEvents extends ApiHelper
{
    public function __construct(
        protected PlayerApiHelper $playerApiHelper,
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param string|int $gameId
     * @param bool $resetCache
     * @return list<CardEventData|GoalEventData|SubstitutionEventData>
     * @throws \Exception
     */
    public function getEvents(string|int $gameId, bool $resetCache): array
    {
        $cacheId = $this->getCacheId($gameId);
        if ($resetCache) {
            $this->resetDataFromCache($cacheId);
        }

        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($gameId),
            $cacheId,
            $this->getCacheMinutes()
        );
        if (!property_exists($apiData, "incidents")) {
            throw new \Exception('apidatarow should containt property "incidents"', E_ERROR);
        }

        /** @var list<stdClass> $eventsApiData */
        $eventsApiData = $apiData->incidents;
//        $eventsApiData = array_filter($eventsApiData, function(stdClass $eventApiData): bool {
//            $eventType = strtolower((string)$eventApiData->incidentType);
//            return in_array(["card", "goal", "penalty") {
//                $createGoalEvent($game, $event);
//            } elseif ($incidentType === "substitution") {
//                $updateGameParticipations($game, $event);
//            }])
//            return $this->convertApiDataRow($eventApiData);
//        });
        $events = [];
        foreach ($eventsApiData as $eventsApiDataRow) {
            $event = $this->convertApiDataRow($eventsApiDataRow);
            if ($event === null) {
                continue;
            }
            $events[] = $event;
        }
        uasort($events, function (AgainstGameEventData $eventA, AgainstGameEventData $eventB): int {
            return $eventA->time < $eventB->time ? -1 : 1;
        });

        return array_values($events);
    }

    protected function convertApiDataRow(stdClass $apiDataRow): CardEventData|GoalEventData|SubstitutionEventData|null
    {
        $eventType = strtolower((string)$apiDataRow->incidentType);

        if ($eventType === "card") {
            return $this->createCard($apiDataRow);
        }
        if ($eventType === "goal" or $eventType === "penalty") {
            return $this->createGoal($apiDataRow);
        }
        if ($eventType === "substitution") {
            return $this->createSubstitution($apiDataRow);
        }
        return null;
    }

    protected function createCard(stdClass $apiDataRow): CardEventData|null
    {
        $eventClass = strtolower((string)$apiDataRow->incidentClass);
        if (!in_array($eventClass, ['yellow', 'yellowred', 'red'], true)) {
            throw new \Exception('kan het kaarttype "' . $eventClass . '" niet vaststellen', E_ERROR);
        }
        if (!property_exists($apiDataRow, 'player')) { // can be manager
            return null;
        }
        /** @var stdClass $playerApiData */
        $playerApiData = $apiDataRow->player;
        $player = $this->convertPlayerApiDataHelper($playerApiData);
        return new CardEventData(
            $player,
            (int)$apiDataRow->time,
            $eventClass === 'red' ? Sport::SENDOFF : Sport::WARNING
        );
    }

    protected function createGoal(stdClass $apiDataRow): GoalEventData
    {
        $eventType = strtolower((string)$apiDataRow->incidentType);
        $eventClass = strtolower((string)$apiDataRow->incidentClass);

        $penalty = false;
        $own = false;
        $assist = null;
        if ($eventType === "goal") {
            if ($eventClass === "owngoal") {
                $own = true;
            } elseif ($eventClass === "penalty") {
                $penalty = true;
            } elseif (property_exists($apiDataRow, 'assist1')) {
                /** @var stdClass $assistApiData */
                $assistApiData = $apiDataRow->assist1;
                $assist = $this->convertPlayerApiDataHelper($assistApiData);
            }
        } elseif ($eventType === "penalty") {
            if ($eventClass === "penalty") {
                $penalty = true;
            }
        }
        /** @var stdClass $playerApiData */
        $playerApiData = $apiDataRow->player;
        $player = $this->convertPlayerApiDataHelper($playerApiData);
        return new GoalEventData($player, (int)$apiDataRow->time, $penalty, $own, $assist);
    }

    protected function createSubstitution(stdClass $apiDataRow): SubstitutionEventData|null
    {
        if ($apiDataRow->playerOut === null || $apiDataRow->playerIn === null ) {
            return null;
        }
        /** @var stdClass $playerOutApiData */
        $playerOutApiData = $apiDataRow->playerOut;
        /** @var stdClass $playerInApiData */
        $playerInApiData = $apiDataRow->playerIn;
        $playerOut = $this->convertPlayerApiDataHelper($playerOutApiData);
        $playerIn = $this->convertPlayerApiDataHelper($playerInApiData);
        return new SubstitutionEventData($playerOut, (int)$apiDataRow->time, $playerIn);
    }

    protected function convertPlayerApiDataHelper(stdClass $playerApiData): PlayerData
    {
        $player = $this->jsonToDataConverter->convertPlayerJsonToData($playerApiData, null);
        if ($player === null) {
            throw new \Exception('player could not be found', E_ERROR);
        }
        return $player;
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
        return "event/**gameId**/incidents";
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
