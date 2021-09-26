<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper\Game;

use DateTimeImmutable;
use Exception;
use League\Period\Period;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Game\Phase as GamePhase;
use Sports\Game\Event\Goal as GoalEvent;
use Sports\Game\Event\Card as CardEvent;
use SportsHelpers\Against\Side as AgainstSide;
use Sports\Sport;
use Sports\Team\Player as TeamPlayer;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGame as AgainstGameData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameRound as AgainstGameRoundData;
use stdClass;
use Sports\Game\Place\Against as AgainstGamePlace;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
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
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent as AgainstGameEventData;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;

/**
 * @template-extends SofaScoreHelper<AgainstGame>
 */
class Against extends SofaScoreHelper implements ExternalSourceAgainstGame
{
    protected CompetitorMap|null $competitorMap = null;

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
        /** @var stdClass $apiEvents */
        $apiEvents = $apiData->events;
        if (!property_exists($apiEvents, "rounds")) {
            return [];
        }
        /** @var list<AgainstGameRoundData> $rounds */
        $rounds = $apiEvents->rounds;
        $gameRoundNumbers = array_map(
            function (AgainstGameRoundData $round): int {
                return $round->round;
            },
            $rounds
        );
        return array_values($gameRoundNumbers);
    }

    /**
     * @param Competition $competition
     * @param int $batchNr
     * @return array<int|string, AgainstGame>
     * @throws Exception
     */
    public function getAgainstGames(Competition $competition, int $batchNr): array
    {
        $competitionGames = [];
        $structure = $this->parent->getStructure($competition);
        if ($structure === null) {
            $this->logger->error('could not find structure for competition ' . $competition->getName());
            return $competitionGames;
        }

        $rootRound = $structure->getFirstRoundNumber()->getRounds()->first();
        $firstPoule = $rootRound === false ? false : $rootRound->getPoules()->first();
        if ($firstPoule === false) {
            $this->logger->error('could not find first poule for competition ' . $competition->getName());
            return $competitionGames;
        }

        $externalGames = $this->apiHelper->getBatchGameData($competition, $batchNr);
        foreach ($externalGames as $externalGame) {
            $externalSourceGame = new AgainstGameData($externalGame);
            $game = $this->convertToAgainstGame($competition, $firstPoule, $externalSourceGame, $batchNr);
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

    protected function convertToAgainstGame(
        Competition $competition,
        Poule $poule,
        AgainstGameData $externalGame,
        int|null $batchNr = null
    ): AgainstGame|null {
        if (array_key_exists($externalGame->event->id, $this->cache)) {
            return $this->cache[$externalGame->event->id];
        }

        $startDateTime = new DateTimeImmutable("@" . $externalGame->event->startTimestamp . "");

        if ($batchNr === null && property_exists($externalGame->event, "roundInfo")) {
            $batchNr = $externalGame->event->roundInfo->round;
        }
        if (!is_int($batchNr)) {
            return null;
        }
        $competitionSport = $competition->getSingleSport();
        $game = new AgainstGame($poule, $batchNr, $startDateTime, $competitionSport, $batchNr);
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

        /** @psalm-suppress RedundantCondition */
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
                    if ($competitor instanceof TeamCompetitor && $externalGame->lineups !== null) {
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

    public function getAgainstGame(Competition $competition, string|int $id): AgainstGame|null
    {
        $externalGame = $this->apiHelper->getGameData($competition, $id);
        $structure = $this->parent->getStructure($competition);
        if ($structure === null) {
            return null;
        }
        $rootRound = $structure->getFirstRoundNumber()->getRounds()->first();
        $firstPoule = $rootRound === false ? false : $rootRound->getPoules()->first();
        return $firstPoule === false ? null : $this->convertToAgainstGame($competition, $firstPoule, $externalGame);
    }

    /**
     * @param AgainstGame $againstGame
     * @param TeamCompetitor $teamCompetitor
     * @param list<PlayerData> $players
     */
    protected function addGameParticipations(AgainstGame $againstGame, TeamCompetitor $teamCompetitor, array $players): void
    {
        $addParticipation = function (PlayerData $externPlayer) use ($againstGame, $teamCompetitor): void {
            if (count($externPlayer->statistics) === 0) {
                return;
            }
            $person = $this->parent->convertToPerson($externPlayer->player);
            if ($person === null) {
                return;
            }
            $seasonEndDateTime = $againstGame->getPoule()->getRound()->getNumber()->getCompetition()->getSeason()->getEndDateTime();
            $period = new Period($againstGame->getStartDateTime(), $seasonEndDateTime);
            if (!property_exists($externPlayer->player, 'position')) {
                throw new \Exception('property position could not be found', E_ERROR);
            }
            if (!is_string($externPlayer->player->position)) {
                throw new \Exception('property position should be string', E_ERROR);
            }
            $line = $this->apiHelper->convertLine($externPlayer->player->position);
            $teamPlayer = new TeamPlayer($teamCompetitor->getTeam(), $person, $period, $line);
            new GameParticipation($againstGame, $teamPlayer, 0, 0);
        };

        foreach ($players as $player) {
            $addParticipation($player);
        }
    }

    /**
     * @param AgainstGame $game
     * @param list<AgainstGameEventData> $events
     */
    protected function addGameEvents(AgainstGame $game, array $events): void
    {
        $createCardEvent = function (AgainstGame $game, AgainstGameEventData $event): void {
            $person = $this->parent->convertToPerson($event->player);
            if ($person === null) {
                throw new Exception("persoon kon niet worden gevonden als speler", E_ERROR);
            }
            $participation = $game->getParticipation($person);
            if ($participation === null) {
                throw new Exception($person->getName() . "(".(string)$person->getId().") kon niet worden gevonden als speler", E_ERROR);
            }
            if ($event->incidentClass === "yellow" || $event->incidentClass === "yellowRed") {
                $card = Sport::WARNING;
            } elseif ($event->incidentClass === "red") {
                $card = Sport::SENDOFF;
            } else {
                throw new Exception("kon het kaarttype \"".$event->incidentClass."\" niet vaststellen", E_ERROR);
            }
            new CardEvent($event->time, $participation, $card);
        };

        $createGoalEvent = function (AgainstGame $game, AgainstGameEventData $event): void {
            $person = $this->parent->convertToPerson($event->player);
            if ($person === null) {
                throw new Exception("persoon kon niet worden gevonden als spelers", E_ERROR);
            }
            $participation = $game->getParticipation($person);
            if ($participation === null) {
                throw new Exception($person->getName() . "(".(string)$person->getId().") kon niet worden gevonden als spelers", E_ERROR);
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
                    if ($personAssist === null) {
                        throw new Exception("persoon kon niet worden gevonden als spelers", E_ERROR);
                    }
                    $assist = $game->getParticipation($personAssist);
                    if ($assist === null) {
                        throw new Exception($personAssist->getName() . "(".(string)$personAssist->getId().") kon niet worden gevonden als spelers", E_ERROR);
                    }
                    $goalEvent->setAssistGameParticipation($assist);
                }
            } elseif ($incidentType === "penalty") {
                if ($incidentClass === "penalty") {
                    $goalEvent->setPenalty(true);
                }
            }
        };

        $updateGameParticipations = function (AgainstGame $game, AgainstGameEventData $event): void {
            $personOut = $this->parent->convertToPerson($event->playerOut);
            if ($personOut === null) {
                throw new Exception("persoon kon niet worden gevonden als speler", E_ERROR);
            }
            $participationOut = $game->getParticipation($personOut);
            if ($participationOut === null) {
                throw new Exception($personOut->getName() . "(".(string)$personOut->getId().") kon niet worden gevonden als speler", E_ERROR);
            }
            $personIn = $this->parent->convertToPerson($event->playerIn);
            if ($personIn === null) {
                throw new Exception("persoon kon niet worden gevonden als speler", E_ERROR);
            }
            $participationIn = $game->getParticipation($personIn);
            if ($participationIn === null) {
                throw new Exception($personIn->getName() . "(".(string)$personIn->getId().") kon niet worden gevonden als speler", E_ERROR);
            }
            $participationOut->setEndMinute($event->time);
            $participationIn->setBeginMinute($event->time);
        };

        uasort($events, function (AgainstGameEventData $eventA, AgainstGameEventData $eventB): int {
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
