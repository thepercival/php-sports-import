<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use DateTimeImmutable;
use League\Period\Period;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Association;
use Sports\Person;
use Sports\Team\Role\Player as TeamPlayer;
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
            $homeCompetitors =  $game->getCompetitors( $placeLocationMap, GameBase::HOME );
            if( $homeCompetitors->count() === 1 ) {
                $homeCompetitor = $homeCompetitors->first();
                if( $homeCompetitor instanceof TeamCompetitor ) {
                    $this->addGameParticipations( $game, $homeCompetitor, $externalGame->lineups->home->players );
                }
            }

//            incidents[0]->incidentType substitution, period, card, goal, inGamePenalty
//            substitution: playerIn, playerOut, time
//            card: incidentClass(yellow), time(21), player
//                goal: incidentClass(penalty, regular), player, assist1
//                inGamePenalty: incidentClass(missed), description(Goalkeeper save), player
        }

        if (property_exists($externalGame, "incidents")) {
            // set incidents
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
    public function addGameParticipations( GameBase $game, TeamCompetitor $teamCompetitor, array $players)
    {
        $addParticipation = function (stdClass $externPlayer ) use ($game, $teamCompetitor): void {

            if (count((array)$externPlayer->statistics) === 0) {
                return;
            }
            $person = $this->parent->convertToPerson( $externPlayer->player );

            $teamPlayer = new TeamPlayer( $teamCompetitor->getTeam(), $person, $game->getPeriod(), $this->apiHelper->convertLine( $externPlayer->player->position ) );

            new GameParticipation( $game, $teamPlayer, 0, 0);
        };

        foreach( $players as $player ) {
            $addParticipation( $player );
        }
//            lineups->home->players->[0]->player->name,slug,id,position GDMF
    }

}
