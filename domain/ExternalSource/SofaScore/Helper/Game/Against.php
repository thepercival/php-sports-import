<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper\Game;

use DateTimeImmutable;
use Exception;
use League\Period\Period;
use Psr\Log\LoggerInterface;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Game\Participation;
use Sports\Game\Phase as GamePhase;
use Sports\Game\Event\Goal as GoalEvent;
use Sports\Game\Event\Card as CardEvent;
use Sports\Person;
use SportsHelpers\Against\Side as AgainstSide;
use Sports\Sport;
use Sports\Team\Player as TeamPlayer;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGames as AgainstGamesApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameDetails as AgainstGameDetailsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameLineups as AgainstGameLineupsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameEvents as AgainstGameEventsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Player as PlayerApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGame as AgainstGameData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Card as CardEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Goal as GoalEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Substitution as SubstitutionEventData;
use stdClass;
use Sports\Game\Place\Against as AgainstGamePlace;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Person as PersonHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Team as TeamHelper;
use Sports\Competitor\Map as CompetitorMap;
use Sports\Competition;
use Sports\Game\Participation as GameParticipation;
use Sports\Poule;
use Sports\Place;
use Sports\Team;
use Sports\Game\Against as AgainstGame;
use Sports\Score\Against as AgainstScore;
use Sports\State;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent as AgainstGameEventData;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;

/**
 * @template-extends SofaScoreHelper<AgainstGame>
 */
class Against extends SofaScoreHelper
{
    protected CompetitorMap|null $competitorMap = null;

    public function __construct(
        protected TeamHelper $teamHelper,
        protected PersonHelper $personHelper,
        protected AgainstGamesApiHelper  $againstGamesApiHelper,
        protected AgainstGameDetailsApiHelper $againstGameDetailsApiHelper,
        protected AgainstGameLineupsApiHelper $againstGameLineupsApiHelper,
        protected AgainstGameEventsApiHelper $againstGameEventsApiHelper,
        protected PlayerApiHelper $playerApiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    protected function getCompetitorMap(Competition $competition): CompetitorMap
    {
        if ($this->competitorMap === null) {
            $this->competitorMap = new CompetitorMap($this->parent->getTeamCompetitors($competition));
        }
        return $this->competitorMap;
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @return array<int|string, AgainstGame>
     * @throws Exception
     */
    public function getAgainstGames(Competition $competition, int $gameRoundNumber): array
    {
        $competitionGames = [];
        $structure = $this->parent->getStructure($competition);
        $rootRound = $structure->getFirstRoundNumber()->getRounds()->first();
        $firstPoule = $rootRound === false ? false : $rootRound->getPoules()->first();
        if ($firstPoule === false) {
            $this->logger->error('could not find first poule for competition ' . $competition->getName());
            return $competitionGames;
        }

        $againstGamesData = $this->againstGamesApiHelper->getAgainstGames($competition, $gameRoundNumber);
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
        $game->setState($againstGameData->status);
        $game->setId($againstGameData->id);
        // referee
        // field

        $competitorMap = $this->getCompetitorMap($competition);
        $association = $competition->getLeague()->getAssociation();
        $homeTeam = $this->teamHelper->convertDataToTeam($association, $againstGameData->homeTeam);
        $homePlace = $this->getPlaceFromPoule($poule, $competitorMap, $homeTeam);
        if ($homePlace === null) {
            return null;
        }
        $awayTeam = $this->teamHelper->convertDataToTeam($association, $againstGameData->awayTeam);
        $awayPlace = $this->getPlaceFromPoule($poule, $competitorMap, $awayTeam);
        if ($awayPlace === null) {
            return null;
        }
        $homeGamePlace = new AgainstGamePlace($game, $homePlace, AgainstSide::Home);
        $awayGamePlace = new AgainstGamePlace($game, $awayPlace, AgainstSide::Away);

        /** @psalm-suppress RedundantCondition */
        if ($game->getState() === State::Finished) {
            $home = $againstGameData->homeScore->current;
            $away = $againstGameData->awayScore->current;
            new AgainstScore($game, $home, $away, GamePhase::RegularTime);
        }

        $lineups = $againstGameData->lineups;
        if ($lineups !== null) {
            foreach ([$homeGamePlace, $awayGamePlace] as $sideGamePlace) {
                // use ($competitorMap, $lineups): void
                $competitors =  $game->getCompetitors($competitorMap, $sideGamePlace->getSide());
                if (count($competitors) === 1) {
                    $competitor = reset($competitors);
                    if ($competitor instanceof TeamCompetitor) {
                        $side = $sideGamePlace->getSide() === AgainstSide::Away ? $lineups->away : $lineups->home;
                        $this->addGameParticipations($sideGamePlace, $competitor, $side->players);
                    }
                }
            };
        }

        $this->addGameEvents($game, $againstGameData->events);

        $gameId = $game->getId();
        if ($gameId !== null) {
            $this->cache[$gameId] = $game;
        }
        return $game;
    }

    protected function getPlaceFromPoule(Poule $poule, CompetitorMap $competitorMap, Team $team): ?Place
    {
        foreach ($poule->getPlaces() as $placeIt) {
            $teamCompetitor = $competitorMap->getCompetitor($placeIt);
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

    public function getAgainstGame(Competition $competition, string|int $id, bool $removeFromGameCache): AgainstGame|null
    {
        $againstGameData = $this->againstGameDetailsApiHelper->getAgainstGame($id, $removeFromGameCache);
        if ($againstGameData === null) {
            return null;
        }
        $structure = $this->parent->getStructure($competition);
        $rootRound = $structure->getFirstRoundNumber()->getRounds()->first();
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
     * @param list<PlayerData> $players
     */
    protected function addGameParticipations(AgainstGamePlace $againstGamePlace, TeamCompetitor $teamCompetitor, array $players): void
    {
        foreach ($players as $playerData) {
//            if (count($externPlayer->statistics) === 0) {
//                return;
//            }
            // $playerData = $this->convertApiDataToPerson($externPlayer->player);
            // $person = $this->con($playerData);
            $game = $againstGamePlace->getGame();
            $seasonEndDateTime = $game->getPoule()->getCompetition()->getSeason()->getEndDateTime();
            $period = new Period($game->getStartDateTime(), $seasonEndDateTime);

            $person = $this->personHelper->convertDataToPerson($playerData);
            if ($person === null) {
                throw new Exception('"'.$playerData->id.'" kon niet worden gevonden als speler', E_ERROR);
            }

            $teamPlayer = new TeamPlayer($teamCompetitor->getTeam(), $person, $period, $playerData->line);

            new GameParticipation($againstGamePlace, $teamPlayer, 0, 0);
        }
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
        $playerData = $this->playerApiHelper->convertApiDataRow($personApiData);
        if ($playerData === null) {
            throw new Exception('"'.(string)$personApiData->id.'" kon niet worden gevonden als speler', E_ERROR);
        }
        $person = $this->personHelper->convertDataToPerson($playerData);
        if ($person === null) {
            throw new Exception('"'.$playerData->id.'" kon niet worden gevonden als speler', E_ERROR);
        }
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
        if ($person === null) {
            throw new Exception('"'.$playerData->id.'" kon niet worden gevonden als speler', E_ERROR);
        }
        $participation = $game->getParticipation($person);
        if ($participation === null) {
            throw new Exception($person->getName() . "(".(string)$person->getId().") kon niet worden gevonden als speler", E_ERROR);
        }
        return $participation;
    }
}
