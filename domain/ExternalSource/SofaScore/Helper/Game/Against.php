<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper\Game;

use Exception;
use League\Period\Period;
use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Competitor;
use Sports\Competitor\StartLocationMap;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Event\Card as CardEvent;
use Sports\Game\Event\Goal as GoalEvent;
use Sports\Game\Participation;
use Sports\Game\Participation as GameParticipation;
use Sports\Game\Phase as GamePhase;
use Sports\Game\Place\Against as AgainstGamePlace;
use Sports\Game\State as GameState;
use Sports\Person;
use Sports\Place;
use Sports\Poule;
use Sports\Score\Against as AgainstScore;
use Sports\Team;
use Sports\Team\Player as TeamPlayer;
use SportsHelpers\Against\Side;
use SportsHelpers\Against\Side as AgainstSide;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGame as AgainstGameApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameEvents as AgainstGameEventsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameLineups as AgainstGameLineupsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGames as AgainstGamesApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\JsonToDataConverter;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGame as AgainstGameData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent as AgainstGameEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Card as CardEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Goal as GoalEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Substitution;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Substitution as SubstitutionEventData;
use SportsImport\ExternalSource\SofaScore\Data\Player;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Person as PersonHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Team as TeamHelper;
use stdClass;

/**
 * @template-extends SofaScoreHelper<AgainstGame>
 */
class Against extends SofaScoreHelper
{
    protected StartLocationMap|null $startLocationMap = null;

    public function __construct(
        protected TeamHelper $teamHelper,
        protected PersonHelper $personHelper,
        protected AgainstGamesApiHelper $againstGamesApiHelper,
        protected AgainstGameApiHelper $againstGameApiHelper,
        protected AgainstGameLineupsApiHelper $againstGameLineupsApiHelper,
        protected AgainstGameEventsApiHelper $againstGameEventsApiHelper,
        protected JsonToDataConverter $jsonToDataConverter,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    protected function getStartLocationMap(Competition $competition): StartLocationMap
    {
        if ($this->startLocationMap === null) {
            $this->startLocationMap = new StartLocationMap($this->parent->getTeamCompetitors($competition));
        }
        return $this->startLocationMap;
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @return array<int|string, AgainstGame>
     * @throws Exception
     */
    public function getAgainstGameBasics(Competition $competition, int $gameRoundNumber): array
    {
        return $this->getAgainstGamesHelper($competition, $gameRoundNumber, true, false);
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @param bool $resetCache
     * @return array<int|string, AgainstGame>
     * @throws Exception
     */
    public function getAgainstGamesComplete(Competition $competition, int $gameRoundNumber, bool $resetCache): array
    {
        return $this->getAgainstGamesHelper($competition, $gameRoundNumber, false, $resetCache);
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @param bool $onlyBasics
     * @param bool $resetCache
     * @return array<int|string, AgainstGame>
     * @throws Exception
     */
    protected function getAgainstGamesHelper(
        Competition $competition,
        int $gameRoundNumber,
        bool $onlyBasics,
        bool $resetCache
    ): array {
        $competitionGames = [];
        $structure = $this->parent->getStructure($competition);
        try {
            $rootRound = $structure->getSingleCategory()->getRootRound();
        }
        catch(Exception $e) {
            $rootRound = false;
        }
        $firstPoule = $rootRound === false ? false : $rootRound->getPoules()->first();
        if ($firstPoule === false) {
            $this->logger->error('could not find first poule for competition ' . $competition->getName());
            return $competitionGames;
        }

        if ($onlyBasics) {
            $againstGamesData = $this->againstGamesApiHelper->getAgainstGamesBasics($competition, $gameRoundNumber);
        } else {
            $againstGamesData = $this->againstGamesApiHelper->getAgainstGamesComplete(
                $competition,
                $gameRoundNumber,
                $resetCache
            );
        }

        foreach ($againstGamesData as $againstGameData) {
            $game = $this->convertDataToAgainstGame($competition, $firstPoule, $againstGameData);
            if ($game === null) {
                continue;
            }
            $gameId = $game->getId();
            if ($gameId === null) {
                continue;
            }
            $competitionGames[$gameId] = $game;
        }
        return $competitionGames;
    }

    protected function convertDataToAgainstGame(
        Competition $competition,
        Poule $poule,
        AgainstGameData $againstGameData
    ): AgainstGame|null {
        if (array_key_exists($againstGameData->id, $this->cache)) {
            return $this->cache[$againstGameData->id];
        }

        $competitionSport = $competition->getSingleSport();
        $gameRoundNumber = $againstGameData->roundInfo->round;
        $game = new AgainstGame($poule, $gameRoundNumber, $againstGameData->start, $competitionSport, $gameRoundNumber);
        $game->setState($againstGameData->state);
        $game->setId($againstGameData->id);
        // referee
        // field

        $startLocationMap = $this->getStartLocationMap($competition);
        $association = $competition->getLeague()->getAssociation();
        $homeTeam = $this->teamHelper->convertDataToTeam($association, $againstGameData->homeTeam);
        $homePlace = $this->getPlaceFromPoule($poule, $startLocationMap, $homeTeam);
        if ($homePlace === null) {
            return null;
        }
        $awayTeam = $this->teamHelper->convertDataToTeam($association, $againstGameData->awayTeam);
        $awayPlace = $this->getPlaceFromPoule($poule, $startLocationMap, $awayTeam);
        if ($awayPlace === null) {
            return null;
        }
        $homeGamePlace = new AgainstGamePlace($game, $homePlace, AgainstSide::Home);
        $awayGamePlace = new AgainstGamePlace($game, $awayPlace, AgainstSide::Away);

        /** @psalm-suppress RedundantCondition */
        if ($game->getState() === GameState::Finished) {
            $home = $againstGameData->homeScore->current;
            $away = $againstGameData->awayScore->current;
            new AgainstScore($game, $home, $away, GamePhase::RegularTime);
        }

        $players = $againstGameData->players;
        if ($players !== null) {
            foreach ([$homeGamePlace, $awayGamePlace] as $sideGamePlace) {
                // use ($competitorMap, $lineups): void
                $competitors =  $this->getGameCompetitors($game, $startLocationMap, $sideGamePlace->getSide());
                $competitor = reset($competitors);
                if (count($competitors) !== 1 || !($competitor instanceof TeamCompetitor)) {
                    continue;
                }
                $sidePlayers = $sideGamePlace->getSide() === AgainstSide::Away ? $players->awayPlayers : $players->homePlayers;
                $this->addAppearedGameParticipations($sideGamePlace, $competitor, $sidePlayers->players);
                $this->addNonAppearedGameParticipationsWithCardEvent($sideGamePlace, $competitor, $againstGameData->events, $sidePlayers->players);
                $this->addAppearedGameParticipationsWithoutMinutesFromSubstituteEvent($sideGamePlace, $competitor, $againstGameData->events, $sidePlayers->players);
            }
        }

        $this->addGameEvents($game, $againstGameData->events);

        $gameId = $game->getId();
        if ($gameId !== null) {
            $this->cache[$gameId] = $game;
        }
        return $game;
    }

    /**
     * @param AgainstGame $game
     * @param StartLocationMap $startLocationMap
     * @param AgainstSide|null $side
     * @return list<Competitor|null>
     */
    public function getGameCompetitors(
        AgainstGame $game, StartLocationMap $startLocationMap, AgainstSide $side = null): array
    {
        return array_map(
            function (AgainstGamePlace $gamePlace) use ($startLocationMap): Competitor|null {
                $startLocation = $gamePlace->getPlace()->getStartLocation();
                return $startLocation !== null ? $startLocationMap->getCompetitor($startLocation) : null;
            },
            $game->getSidePlaces($side)
        );
    }

    protected function getPlaceFromPoule(Poule $poule, StartLocationMap $startLocationMap, Team $team): ?Place
    {
        foreach ($poule->getPlaces() as $placeIt) {
            $startLocation = $placeIt->getStartLocation();
            if ($startLocation === null) {
                return null;
            }
            $teamCompetitor = $startLocationMap->getCompetitor($startLocation);
            if ($teamCompetitor === null) {
                return null;
            }
            if (!($teamCompetitor instanceof TeamCompetitor)) {
                return null;
            }
            if ($teamCompetitor->getTeam() === $team) {
                return $placeIt;
            }
        }
        return null;
    }

    public function getAgainstGame(Competition $competition, string|int $id, bool $resetCache): AgainstGame|null
    {
        $againstGameData = $this->againstGameApiHelper->getAgainstGame($id, $resetCache);
        if ($againstGameData === null) {
            return null;
        }
        $structure = $this->parent->getStructure($competition);
        try {
            $rootRound = $structure->getSingleCategory()->getRootRound();
        }
        catch(Exception $e) {
            $rootRound = false;
        }
        $firstPoule = $rootRound === false ? false : $rootRound->getPoules()->first();
        return $firstPoule === false ? null : $this->convertDataToAgainstGame(
            $competition,
            $firstPoule,
            $againstGameData
        );
    }

    /**
     * @param AgainstGamePlace $againstGamePlace
     * @param TeamCompetitor $teamCompetitor
     * @param list<PlayerData> $playersData
     */
    protected function addAppearedGameParticipations(
        AgainstGamePlace $againstGamePlace,
        TeamCompetitor $teamCompetitor,
        array $playersData
    ): void
    {
        foreach ($playersData as $playerData) {
            if ($playerData->nrOfMinutesPlayed === 0) {
                continue;
            }
            $this->createGameParticipation($againstGamePlace, $teamCompetitor, $playerData);
        }
    }

    /**
     * @param AgainstGamePlace $againstGamePlace
     * @param TeamCompetitor $teamCompetitor
     * @param list<CardEventData|GoalEventData|SubstitutionEventData> $eventsData
     * @param list<PlayerData> $sidePlayersData
     * @return void
     */
    protected function addNonAppearedGameParticipationsWithCardEvent(
        AgainstGamePlace $againstGamePlace,
        TeamCompetitor $teamCompetitor,
        array $eventsData,
        array $sidePlayersData
    ): void
    {
        foreach ($eventsData as $eventData) {
            if (!($eventData instanceof CardEventData)) {
                continue;
            }
            $eventPlayer = $eventData->player;
            $sidePlayers = array_filter($sidePlayersData, function (PlayerData $playerData) use ($eventPlayer): bool {
                return $playerData->id === $eventPlayer->id;
            });
            $sidePlayer = reset($sidePlayers);
            if ($sidePlayer !== false && $sidePlayer->nrOfMinutesPlayed === 0) {
                $this->createGameParticipation($againstGamePlace, $teamCompetitor, $eventData->player);
            }
        }
    }
    /**
     * @param AgainstGamePlace $againstGamePlace
     * @param TeamCompetitor $teamCompetitor
     * @param list<CardEventData|GoalEventData|SubstitutionEventData> $eventsData
     * @param list<PlayerData> $sidePlayersData
     * @return void
     */
    protected function addAppearedGameParticipationsWithoutMinutesFromSubstituteEvent(
        AgainstGamePlace $againstGamePlace,
        TeamCompetitor $teamCompetitor,
        array $eventsData,
        array $sidePlayersData
    ): void
    {
        foreach ($eventsData as $eventData) {
            if (!($eventData instanceof SubstitutionEventData)) {
                continue;
            }
            if( $eventData->substitute->nrOfMinutesPlayed > 0 ) {
                continue;
            }

            $foundPlayers = array_filter($sidePlayersData, function (Player $player) use ($eventData): bool {
                return $eventData->player->id == $player->id;
            });
            if (count($foundPlayers) === 0) {
                continue;
            }
            $person = $this->personHelper->convertDataToPerson($eventData->substitute);
            $foundSubs = $againstGamePlace->getParticipations()->filter(
                function (GameParticipation $gameParticipation) use ($person): bool {
                    return $gameParticipation->getPlayer()->getPerson()->getId() == $person->getId();
                }
            );
            if (count($foundSubs) > 0) {
                continue;
            }
            $this->createGameParticipation($againstGamePlace, $teamCompetitor, $eventData->substitute);
        }
    }

    protected function createGameParticipation(AgainstGamePlace $againstGamePlace, TeamCompetitor $teamCompetitor, PlayerData $playerData): GameParticipation
    {
        $game = $againstGamePlace->getGame();
        $period = new Period($game->getStartDateTime(), $game->getStartDateTime()->add(new \DateInterval('PT3H')));

        $person = $this->personHelper->convertDataToPerson($playerData);
//        if ($person === null) {
//            throw new Exception('"'.$playerData->id.'" kon niet worden gevonden als speler', E_ERROR);
//        }

        $teamPlayer = new TeamPlayer($teamCompetitor->getTeam(), $person, $period, $playerData->line->value);
        $teamPlayer->setMarketValue($playerData->marketValue);

        $beginMinute = $playerData->nrOfMinutesPlayed === 0 ? -1 : 0;
        return new GameParticipation($againstGamePlace, $teamPlayer, $beginMinute);
    }

    /**
     * @param AgainstGame $game
     * @param list<CardEventData|GoalEventData|SubstitutionEventData> $events
     */
    protected function addGameEvents(AgainstGame $game, array $events): void
    {
        $createCardEvent = function (AgainstGame $game, CardEventData $event): void {
            $participation = $this->convertPlayerDataToParticipation($game, $event->player);
            new CardEvent($event->time, $participation, $event->color);
        };

        $createGoalEvent = function (AgainstGame $game, GoalEventData $event): void {
            $participation = $this->convertPlayerDataToParticipation($game, $event->player);
            $goalEvent = new GoalEvent($event->time, $participation);
            $goalEvent->setOwn($event->own);
            $goalEvent->setPenalty($event->penalty);
            if ($event->assist) {
                $assistGameParticipation = $this->convertPlayerDataToParticipation($game, $event->assist);
                $goalEvent->setAssistGameParticipation($assistGameParticipation);
            }
        };

        $updateGameParticipations = function (AgainstGame $game, SubstitutionEventData $event): void {
            $participationOut = $this->convertPlayerDataToParticipation($game, $event->player);
            $participationIn = $this->convertPlayerDataToParticipation($game, $event->substitute);
            $participationOut->setEndMinute($event->time);
            $participationIn->setBeginMinute($event->time);
        };

        uasort($events, function (AgainstGameEventData $eventA, AgainstGameEventData $eventB): int {
            return $eventA->time < $eventB->time ? -1 : 1;
        });
        foreach ($events as $event) {
            if ($event instanceof CardEventData) {
                $createCardEvent($game, $event);
            } elseif ($event instanceof GoalEventData) {
                $createGoalEvent($game, $event);
            } else {
                $updateGameParticipations($game, $event);
            }
        }
    }

    protected function convertApiDataToPerson(stdClass $personApiData): Person
    {
        $playerData = $this->jsonToDataConverter->convertPlayerJsonToData($personApiData, null);
        if ($playerData === null) {
            throw new Exception('"' . (string)$personApiData->id . '" kon niet worden gevonden als speler', E_ERROR);
        }
        $person = $this->personHelper->convertDataToPerson($playerData);
//        if ($person === null) {
//            throw new Exception('"'.$playerData->id.'" kon niet worden gevonden als speler', E_ERROR);
//        }
        return $person;
    }

    protected function convertPersonApiDataToParticipation(AgainstGame $game, stdClass $personApiData): Participation
    {
        $person = $this->convertApiDataToPerson($personApiData);
        $participation = $game->getParticipation($person);
        if ($participation === null) {
            throw new Exception($person->getName() . "(".(string)$person->getId().") kon niet worden gevonden als speler", E_ERROR);
        }
        return $participation;
    }

    protected function convertPlayerDataToParticipation(AgainstGame $game, PlayerData $playerData): Participation
    {
        $person = $this->personHelper->convertDataToPerson($playerData);
//        if ($person === null) {
//            throw new Exception('"'.$playerData->id.'" kon niet worden gevonden als speler', E_ERROR);
//        }
        $participation = $game->getParticipation($person);
        if ($participation === null) {
            throw new Exception(
                $person->getName() . "(" . (string)$person->getId() . ") kon niet worden gevonden als speler", E_ERROR
            );
        }
        return $participation;
    }
}
