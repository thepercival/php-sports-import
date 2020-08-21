<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use DateTimeImmutable;
use Sports\Competitor\Team as TeamCompetitor;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Game as GameBase;
use Psr\Log\LoggerInterface;
use Sports\Place\Location\Map as PlaceLocationMap;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use SportsImport\ExternalSource\Game as ExternalSourceGame;
use Sports\Poule;
use Sports\Place;
use Sports\Team;
use Sports\State;

class Game extends SofaScoreHelper implements ExternalSourceGame
{
    /**
     * @var PlaceLocationMap|null
     */
    protected $placeLocationMap;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    protected function getPlaceLocationMap( Competition $competition ): PlaceLocationMap {
        if( $this->placeLocationMap === null ) {
            $this->placeLocationMap = new PlaceLocationMap( $this->parent->getTeamCompetitors($competition) );
        }
        return $this->placeLocationMap;
    }

    /**
     * @param Competition $competition
     * @return array|int[]
     */
    public function getBatchNrs(Competition $competition ): array
    {
        $apiData = $this->apiHelper->getStructureData($competition);

        if (!property_exists($apiData, "events")) {
            return [];
        }
        if (!property_exists($apiData->events, "rounds")) {
            return [];
        }
        $gameRoundNumbers = array_map(function (stdClass $round) {
            return $round->round;
        }, $apiData->events->rounds);
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
        $association = $competition->getLeague()->getAssociation();
        $structure = $this->parent->getStructure($competition);
        $poule = $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();
        $placeLocationMap = $this->getPlaceLocationMap($competition);

        $apiData = $this->apiHelper->getBatchGameData($competition, $batchNr);
        if (!property_exists($apiData, "roundMatches")) {
            return $competitionGames;
        }
        if (!property_exists($apiData->roundMatches, "tournaments")) {
            return $competitionGames;
        }
        if (count($apiData->roundMatches->tournaments) !== 1) {
            return $competitionGames;
        }
        $tournament = reset($apiData->roundMatches->tournaments);
        if (!property_exists($tournament, "events")) {
            return $competitionGames;
        }
        /** @var stdClass $externalSourceGame */
        foreach ($tournament->events as $externalSourceGame) {
            $startDateTime = new DateTimeImmutable("@".$externalSourceGame->startTimestamp."");

            $game = new GameBase($poule, $batchNr, $startDateTime);
            if (property_exists($externalSourceGame, "status")) {
                $game->setState($this->convertState($externalSourceGame->status->code));
            }
            $game->setId($externalSourceGame->id);
            // referee
            // field

            $homeTeam = $this->apiHelper->convertTeam($association, $externalSourceGame->homeTeam);
            $homePlace = $this->getPlaceFromPoule($poule, $placeLocationMap, $homeTeam);
            if ($homePlace === null) {
                continue;
            }
            $awayTeam = $this->apiHelper->convertTeam($association, $externalSourceGame->awayTeam);
            $awayPlace = $this->getPlaceFromPoule($poule, $placeLocationMap, $awayTeam);
            if ($awayPlace === null) {
                continue;
            }
            $game->addPlace($homePlace, GameBase::HOME);
            $game->addPlace($awayPlace, GameBase::AWAY);

            if ($game->getState() === State::Finished and is_object($externalSourceGame->homeScore)) {
                $home = $externalSourceGame->homeScore->current;
                $away = $externalSourceGame->awayScore->current;
                new GameBase\Score($game, $home, $away, GameBase::PHASE_REGULARTIME);
            }

            $competitionGames[$game->getId()] = $game;
        }
        return $competitionGames;
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
        foreach( $poule->getPlaces() as $placeIt ) {
            $teamCompetitor = $placeLocationMap->getCompetitor($placeIt);
            if( $teamCompetitor === null ) {
                return null;
            }
            if( !($teamCompetitor instanceof TeamCompetitor)) {
                return null;
            }
            if( $teamCompetitor->getTeam() === $team ) {
                return $placeIt;
            }
        }
        return null;
    }
}
