<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use DateTimeImmutable;
use League\Period\Period;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Game\Event\Goal as GoalEvent;
use Sports\Game\Event\Card as CardEvent;
use Sports\Person;
use Sports\Sport;
use Sports\Team\Player as TeamPlayer;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Game as GameBase;
use Psr\Log\LoggerInterface;
use Sports\Place\Location\Map as PlaceLocationMap;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use Sports\Game\Participation as GameParticipation;
use SportsImport\ExternalSource\Game as ExternalSourceGame;
use Sports\Poule;
use Sports\Place;
use Sports\Team;
use Sports\State;

class Game extends SofaScoreHelper implements ExternalSourceGame
{
    /**
     * @var array|GameBase[]
     */
    protected $gameCache;
    /**
     * @var PlaceLocationMap|null
     */
    protected $placeLocationMap;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->gameCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    protected function getPlaceLocationMap(Competition $competition): PlaceLocationMap
    {
        if ($this->placeLocationMap === null) {
            $this->placeLocationMap = new PlaceLocationMap($this->parent->getTeamCompetitors($competition));
        }
        return $this->placeLocationMap;
    }

    /**
     * @param Competition $competition
     * @return array|int[]
     */
    public function getBatchNrs(Competition $competition): array
    {
        $apiData = $this->apiHelper->getStructureData($competition);

        if (!property_exists($apiData, "events")) {
            return [];
        }
        if (!property_exists($apiData->events, "rounds")) {
            return [];
        }
        $gameRoundNumbers = array_map(
            function (stdClass $round) {
                return $round->round;
            },
            $apiData->events->rounds
        );
        return $gameRoundNumbers;
    }

    /**
     * @param Competition $competition
     * @param int $batchNr
     * @return array|GameBase[]
     * @throws \Exception
     */
    public function getGames(Competition $competition, int $batchNr): array
    {
        $competitionGames = [];
        $structure = $this->parent->getStructure($competition);
        $poule = $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();

        $externalGames = $this->apiHelper->getBatchGameData($competition, $batchNr);

        /** @var stdClass $externalGame */
        foreach ($externalGames as $externalGame) {
            $externalSourceGame = new stdClass();
            $externalSourceGame->event = $externalGame;
            $game = $this->convertToGame($competition, $poule, $externalSourceGame, $batchNr);
            if ($game === null) {
                continue;
            }
            $competitionGames[$game->getId()] = $game;
        }
        return $competitionGames;
    }

    protected function convertToGame(
        Competition $competition,
        Poule $poule,
        stdClass $externalGame,
        int $batchNr = null
    ): ?GameBase {
        if( array_key_exists( $externalGame->event->id, $this->gameCache ) ) {
            return $this->gameCache[$externalGame->event->id];
        }

        $startDateTime = new DateTimeImmutable("@" . $externalGame->event->startTimestamp . "");

        if ($batchNr === null) {
            if (!property_exists($externalGame->event, "roundInfo")) {
                return null;
            }
            $batchNr = $externalGame->event->roundInfo->round;
        }
        $game = new GameBase($poule, $batchNr, $startDateTime);
        if (property_exists($externalGame->event, "status")) {
            $game->setState($this->convertState($externalGame->event->status->code));
        }
        $game->setId($externalGame->event->id);
        // referee
        // field

        $placeLocationMap = $this->getPlaceLocationMap($competition);
        $association = $competition->getLeague()->getAssociation();
        $homeTeam = $this->apiHelper->convertTeam($association, $externalGame->event->homeTeam);
        $homePlace = $this->getPlaceFromPoule($poule, $placeLocationMap, $homeTeam);
        if ($homePlace === null) {
            return null;
        }
        $awayTeam = $this->apiHelper->convertTeam($association, $externalGame->event->awayTeam);
        $awayPlace = $this->getPlaceFromPoule($poule, $placeLocationMap, $awayTeam);
        if ($awayPlace === null) {
            return null;
        }
        $game->addPlace($homePlace, GameBase::HOME);
        $game->addPlace($awayPlace, GameBase::AWAY);

        if ($game->getState() === State::Finished and is_object($externalGame->event->homeScore)) {
            $home = $externalGame->event->homeScore->current;
            $away = $externalGame->event->awayScore->current;
            new GameBase\Score($game, $home, $away, GameBase::PHASE_REGULARTIME);
        }

        if (property_exists($externalGame, "lineups")) {
            $addParticipations = function( bool $homeAway ) use ( $game, $placeLocationMap, $externalGame ): void {
                $competitors =  $game->getCompetitors( $placeLocationMap, $homeAway );
                if( $competitors->count() === 1 ) {
                    $competitor = $competitors->first();
                    if( $competitor instanceof TeamCompetitor ) {
                        $externalPlayers = $externalGame->lineups->home->players;
                        if( $homeAway === GameBase::AWAY ) {
                            $externalPlayers = $externalGame->lineups->away->players;
                        }
                        $this->addGameParticipations( $game, $competitor, $externalPlayers );
                    }
                }
            };
            $addParticipations( GameBase::HOME );
            $addParticipations( GameBase::AWAY );
        }

        if (property_exists($externalGame, "incidents")) {
            $this->addGameEvents( $game, $externalGame->incidents );
        }

        $this->gameCache[$game->getId()] = $game;
        return $game;
    }

    protected function convertState(int $state): int
    {
        if ($state === 0) { // not started
            return State::Created;
        } elseif ($state === 60) { // postponed
            return State::Canceled;
        } elseif ($state === 70) { // canceled
            return State::Canceled;
        } elseif ($state === 100) { // finished
            return State::Finished;
        }
        throw new \Exception("unknown sofascore-status: " . $state, E_ERROR);
    }

    protected function getPlaceFromPoule(Poule $poule, PlaceLocationMap $placeLocationMap, Team $team): ?Place
    {
        foreach ($poule->getPlaces() as $placeIt) {
            $teamCompetitor = $placeLocationMap->getCompetitor($placeIt);
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

    public function getGame(Competition $competition, $id): ?GameBase
    {
        $externalGame = $this->apiHelper->getGameData($competition, $id);
        $structure = $this->parent->getStructure($competition);
        $poule = $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();
        return $this->convertToGame($competition, $poule, $externalGame);
    }

    /**
     * @param GameBase $game
     * @param TeamCompetitor $teamCompetitor
     * @param array|stdClass[] $players
     */
    protected function addGameParticipations( GameBase $game, TeamCompetitor $teamCompetitor, array $players)
    {
        $addParticipation = function (stdClass $externPlayer ) use ($game, $teamCompetitor): void {
            if (count((array)$externPlayer->statistics) === 0) {
                return;
            }
            $person = $this->parent->convertToPerson( $externPlayer->player );
            $seasonEndDateTime = $game->getPoule()->getRound()->getNumber()->getCompetition()->getSeason()->getEndDateTime();
            $period = new Period( $game->getStartDateTime(), $seasonEndDateTime );
            $line = $this->apiHelper->convertLine( $externPlayer->player->position );
            $teamPlayer = new TeamPlayer( $teamCompetitor->getTeam(), $person, $period, $line );
            new GameParticipation( $game, $teamPlayer, 0, 0);
        };

        foreach( $players as $player ) {
            $addParticipation( $player );
        }
    }

    /**
     * @param GameBase $game
     * @param array|stdClass[] $events
     */
    protected function addGameEvents( GameBase $game, array $events)
    {
        $createCardEvent = function( GameBase $game, stdClass $event ): void {
            $person = $this->parent->convertToPerson( $event->player );
            $participation = $game->getParticipation( $person );
            if( $participation === null ) {
                throw new \Exception( $person->getName() . "(".$person->getId().") kon niet worden gevonden als spelers", E_ERROR );
            }
            $card = null;
            if( $event->incidentClass === "yellow" || $event->incidentClass === "yellowRed" ) {
                $card = Sport::WARNING;
            } else if( $event->incidentClass === "red" ) {
                $card = Sport::SENDOFF;
            } else {
                throw new \Exception( "kon het kaarttype \"".$event->incidentClass."\" niet vaststellen", E_ERROR );
            }
            new CardEvent( $event->time, $participation, $card );
        };

        $createGoalEvent = function( GameBase $game, stdClass $event ): void {
            $person = $this->parent->convertToPerson( $event->player );
            $participation = $game->getParticipation( $person );
            if( $participation === null ) {
                throw new \Exception( $person->getName() . "(".$person->getId().") kon niet worden gevonden als spelers", E_ERROR );
            }
            $goalEvent = new GoalEvent( $event->time, $participation );
            $incidentType = strtolower($event->incidentType);
            $incidentClass = strtolower($event->incidentClass);
            if( $incidentType === "goal" ) {
                if( $incidentClass === "owngoal" ) {
                    $goalEvent->setOwn( true );
                } else if( $incidentClass === "penalty" ) {
                    $goalEvent->setPenalty( true );
                } else if( property_exists( $event, "assist1") ) {
                    $personAssist = $this->parent->convertToPerson( $event->assist1 );
                    $assist = $game->getParticipation( $personAssist );
                    if( $assist === null ) {
                        throw new \Exception( $personAssist->getName() . "(".$personAssist->getId().") kon niet worden gevonden als spelers", E_ERROR );
                    }
                    $goalEvent->setAssistGameParticipation($assist);
                }
            } else if ( $incidentType === "penalty" ) {
                if( $incidentClass === "penalty" ) {
                    $goalEvent->setPenalty( true );
                }
            }
        };

        $updateGameParticipations = function( GameBase $game, stdClass $event ): void {
            $personOut = $this->parent->convertToPerson( $event->playerOut );
            $participationOut = $game->getParticipation( $personOut );
            if( $participationOut === null ) {
                throw new \Exception( $personOut->getName() . "(".$personOut->getId().") kon niet worden gevonden als spelers", E_ERROR );
            }
            $personIn = $this->parent->convertToPerson( $event->playerIn );
            $participationIn = $game->getParticipation( $personIn );
            if( $participationIn === null ) {
                throw new \Exception( $personIn->getName() . "(".$personIn->getId().") kon niet worden gevonden als spelers", E_ERROR );
            }
            $participationOut->setEndMinute( $event->time );
            $participationIn->setBeginMinute( $event->time );
        };

        uasort( $events, function ( $eventA, $eventB ): int {
            return $eventA->time < $eventB->time ? -1 : 1;
        });

        foreach( $events as $event ) {
            $incidentType = strtolower($event->incidentType);
            if( $incidentType === "card" ) {
                $createCardEvent( $game, $event );
            } else if( $incidentType === "goal" or $incidentType === "penalty" ) {
                $createGoalEvent( $game, $event );
            } else if( $incidentType === "substitution" ) {
                $updateGameParticipations( $game, $event );
            }
        }
    }
}
