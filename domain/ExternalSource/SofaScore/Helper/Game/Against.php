<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper\Game;

use DateTimeImmutable;
use League\Period\Period;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Game\Phase as GamePhase;
use Sports\Game\Event\Goal as GoalEvent;
use Sports\Game\Event\Card as CardEvent;
use SportsHelpers\Against\Side as AgainstSide;
use Sports\Sport;
use Sports\Team\Player as TeamPlayer;
use stdClass;
use Sports\Game\Place\Against as AgainstGamePlace;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Game as GameBase;
use Psr\Log\LoggerInterface;
use Sports\Competitor\Map as CompetitorMap;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use Sports\Game\Participation as GameParticipation;
use SportsImport\ExternalSource\Game\Against as ExternalSourceAgainstGame;
use Sports\Poule;
use Sports\Place;
use Sports\Team;
use Sports\Game\Against as AgainstGame;
use Sports\Score\Against as AgainstScore;
use Sports\State;

class Against extends SofaScoreHelper implements ExternalSourceAgainstGame
{
    /**
     * @var array|GameBase[]
     */
    protected $gameCache;
    protected CompetitorMap|null $placeLocationMap = null;

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

    protected function getCompetitorMap(Competition $competition): CompetitorMap
    {
        if ($this->competitorMap === null) {
            $this->competitorMap = new CompetitorMap($this->parent->getTeamCompetitors($competition));
        }
        return $this->competitorMap;
    }

    /**
     * @param Competition $competition
     * @return list<int>
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
        return array_values($gameRoundNumbers);
    }

    /**
     * @param Competition $competition
     * @param int $batchNr
     * @return list<AgainstGame>
     * @throws \Exception
     */
    public function getAgainstGames(Competition $competition, int $batchNr): array
    {
        $competitionGames = [];
        $structure = $this->parent->getStructure($competition);
        $poule = $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();

        $externalGames = $this->apiHelper->getBatchGameData($competition, $batchNr);

        /** @var stdClass $externalGame */
        foreach ($externalGames as $externalGame) {
            $externalSourceGame = new stdClass();
            $externalSourceGame->event = $externalGame;
            $game = $this->convertToAgainstGame($competition, $poule, $externalSourceGame, $batchNr);
            if ($game === null) {
                continue;
            }
            $competitionGames[$game->getId()] = $game;
        }
        return $competitionGames;
    }

    protected function convertToAgainstGame(
        Competition $competition,
        Poule $poule,
        stdClass $externalGame,
        int $batchNr = null
    ): AgainstGame|null {
        if (array_key_exists($externalGame->event->id, $this->gameCache)) {
            return $this->gameCache[$externalGame->event->id];
        }

        $startDateTime = new DateTimeImmutable("@" . $externalGame->event->startTimestamp . "");

        if ($batchNr === null) {
            if (!property_exists($externalGame->event, "roundInfo")) {
                return null;
            }
            $batchNr = $externalGame->event->roundInfo->round;
        }
        $game = new AgainstGame($poule, $batchNr, $startDateTime);
        if (property_exists($externalGame->event, "status")) {
            $game->setState($this->apiHelper->convertState($externalGame->event->status->code));
        }
        $game->setId($externalGame->event->id);
        // referee
        // field

        $competitorMap = $this->getCompetitorMap($competition);
        $association = $competition->getLeague()->getAssociation();
        $homeTeam = $this->apiHelper->convertTeam($association, $externalGame->event->homeTeam);
        $homePlace = $this->getPlaceFromPoule($poule, $competitorMap, $homeTeam);
        if ($homePlace === null) {
            return null;
        }
        $awayTeam = $this->apiHelper->convertTeam($association, $externalGame->event->awayTeam);
        $awayPlace = $this->getPlaceFromPoule($poule, $competitorMap, $awayTeam);
        if ($awayPlace === null) {
            return null;
        }
        $game->getPlaces()->add(new AgainstGamePlace($game, $homePlace, AgainstSide::HOME));
        $game->getPlaces()->add(new AgainstGamePlace($game, $awayPlace, AgainstSide::AWAY));

        if ($game->getState() === State::Finished and is_object($externalGame->event->homeScore)) {
            $home = $externalGame->event->homeScore->current;
            $away = $externalGame->event->awayScore->current;
            new AgainstScore($game, $home, $away, GamePhase::RegularTime);
        }

        if (property_exists($externalGame, "lineups")) {
            $addParticipations = function (int $homeAway) use ($game, $competitorMap, $externalGame): void {
                $competitors =  $game->getCompetitors($competitorMap, $homeAway);
                if (count($competitors) === 1) {
                    $competitor = reset($competitors);
                    if ($competitor instanceof TeamCompetitor) {
                        $externalPlayers = $externalGame->lineups->home->players;
                        if ($homeAway === AgainstSide::AWAY) {
                            $externalPlayers = $externalGame->lineups->away->players;
                        }
                        $this->addGameParticipations($game, $competitor, $externalPlayers);
                    }
                }
            };
            $addParticipations(AgainstSide::HOME);
            $addParticipations(AgainstSide::AWAY);
        }

        if (property_exists($externalGame, "incidents")) {
            $this->addGameEvents($game, $externalGame->incidents);
        }

        $this->gameCache[$game->getId()] = $game;
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

    public function getAgainstGame(Competition $competition, $id): AgainstGame|null
    {
        $externalGame = $this->apiHelper->getGameData($competition, $id);
        $structure = $this->parent->getStructure($competition);
        $poule = $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();
        return $this->convertToAgainstGame($competition, $poule, $externalGame);
    }

    /**
     * @param AgainstGame $againstGame
     * @param TeamCompetitor $teamCompetitor
     * @param array|stdClass[] $players
     */
    protected function addGameParticipations(AgainstGame $againstGame, TeamCompetitor $teamCompetitor, array $players)
    {
        $addParticipation = function (stdClass $externPlayer) use ($againstGame, $teamCompetitor): void {
            if (count((array)$externPlayer->statistics) === 0) {
                return;
            }
            $person = $this->parent->convertToPerson($externPlayer->player);
            $seasonEndDateTime = $againstGame->getPoule()->getRound()->getNumber()->getCompetition()->getSeason()->getEndDateTime();
            $period = new Period($againstGame->getStartDateTime(), $seasonEndDateTime);
            $line = $this->apiHelper->convertLine($externPlayer->player->position);
            $teamPlayer = new TeamPlayer($teamCompetitor->getTeam(), $person, $period, $line);
            new GameParticipation($againstGame, $teamPlayer, 0, 0);
        };

        foreach ($players as $player) {
            $addParticipation($player);
        }
    }

    /**
     * @param GameBase $game
     * @param array|stdClass[] $events
     */
    protected function addGameEvents(GameBase $game, array $events)
    {
        $createCardEvent = function (GameBase $game, stdClass $event): void {
            $person = $this->parent->convertToPerson($event->player);
            $participation = $game->getParticipation($person);
            if ($participation === null) {
                throw new \Exception($person->getName() . "(".$person->getId().") kon niet worden gevonden als spelers", E_ERROR);
            }
            $card = null;
            if ($event->incidentClass === "yellow" || $event->incidentClass === "yellowRed") {
                $card = Sport::WARNING;
            } elseif ($event->incidentClass === "red") {
                $card = Sport::SENDOFF;
            } else {
                throw new \Exception("kon het kaarttype \"".$event->incidentClass."\" niet vaststellen", E_ERROR);
            }
            new CardEvent($event->time, $participation, $card);
        };

        $createGoalEvent = function (GameBase $game, stdClass $event): void {
            $person = $this->parent->convertToPerson($event->player);
            $participation = $game->getParticipation($person);
            if ($participation === null) {
                throw new \Exception($person->getName() . "(".$person->getId().") kon niet worden gevonden als spelers", E_ERROR);
            }
            $goalEvent = new GoalEvent($event->time, $participation);
            $incidentType = strtolower($event->incidentType);
            $incidentClass = strtolower($event->incidentClass);
            if ($incidentType === "goal") {
                if ($incidentClass === "owngoal") {
                    $goalEvent->setOwn(true);
                } elseif ($incidentClass === "penalty") {
                    $goalEvent->setPenalty(true);
                } elseif (property_exists($event, "assist1")) {
                    $personAssist = $this->parent->convertToPerson($event->assist1);
                    $assist = $game->getParticipation($personAssist);
                    if ($assist === null) {
                        throw new \Exception($personAssist->getName() . "(".$personAssist->getId().") kon niet worden gevonden als spelers", E_ERROR);
                    }
                    $goalEvent->setAssistGameParticipation($assist);
                }
            } elseif ($incidentType === "penalty") {
                if ($incidentClass === "penalty") {
                    $goalEvent->setPenalty(true);
                }
            }
        };

        $updateGameParticipations = function (GameBase $game, stdClass $event): void {
            $personOut = $this->parent->convertToPerson($event->playerOut);
            $participationOut = $game->getParticipation($personOut);
            if ($participationOut === null) {
                throw new \Exception($personOut->getName() . "(".$personOut->getId().") kon niet worden gevonden als spelers", E_ERROR);
            }
            $personIn = $this->parent->convertToPerson($event->playerIn);
            $participationIn = $game->getParticipation($personIn);
            if ($participationIn === null) {
                throw new \Exception($personIn->getName() . "(".$personIn->getId().") kon niet worden gevonden als spelers", E_ERROR);
            }
            $participationOut->setEndMinute($event->time);
            $participationIn->setBeginMinute($event->time);
        };

        uasort($events, function ($eventA, $eventB): int {
            return $eventA->time < $eventB->time ? -1 : 1;
        });

        foreach ($events as $event) {
            $incidentType = strtolower($event->incidentType);
            if ($incidentType === "card") {
                $createCardEvent($game, $event);
            } elseif ($incidentType === "goal" or $incidentType === "penalty") {
                $createGoalEvent($game, $event);
            } elseif ($incidentType === "substitution") {
                $updateGameParticipations($game, $event);
            }
        }
    }
}
