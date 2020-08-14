<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use DateTimeImmutable;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Game as GameBase;
use Psr\Log\LoggerInterface;
use SportsImport\Import\Service as ImportService;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use SportsImport\ExternalSource\Game as ExternalSourceGame;
use Sports\Poule;
use Sports\Place;
use Sports\Competitor;
use Sports\State;
use SportsImport\Structure as StructureBase;

class Game extends SofaScoreHelper implements ExternalSourceGame
{
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

    // wedstrijden
    // events->rounds heeft het aantal ronden, dit is per competitie op te vragen
    // per wedstrijdronde de games invoeren, voor de ronden die nog niet ingevoerd zijn

    // wedstrijden te updaten uit aparte url per wedstrijdronde
    // roundMatches->tournaments[]->events[]

    /**
     * @param Competition $competition
     * @param bool $forImport
     * @return array|int[]
     */
    public function getBatchNrs(Competition $competition, bool $forImport): array
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
        if ($forImport !== true) {
            return $gameRoundNumbers;
        }
        $nDayOfWeek = (int)(new \DateTimeImmutable())->format("w");
        return array_filter($gameRoundNumbers, function (int $gameRoundNumber) use ($nDayOfWeek): bool {
            return ($gameRoundNumber % 7) === $nDayOfWeek;
        });
    }

    public function getGames(Competition $competition, int $batchNr): array
    {
        $competitionGames = [];
        $association = $competition->getLeague()->getAssociation();
        $structure = $this->parent->getStructure($competition);
        $poule = $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();

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

            // @TODO DEPRECATED
//            $homeCompetitor = $this->apiHelper->convertCompetitor($association, $externalSourceGame->homeTeam);
//            $homePlace = $this->getPlaceFromPoule($poule, $homeCompetitor);
//            if ($homePlace === null) {
//                continue;
//            }
//            $awayCompetitor = $this->apiHelper->convertCompetitor($association, $externalSourceGame->awayTeam);
//            $awayPlace = $this->getPlaceFromPoule($poule, $awayCompetitor);
//            if ($awayPlace === null) {
//                continue;
//            }
//            $game->addPlace($homePlace, GameBase::HOME);
//            $game->addPlace($awayPlace, GameBase::AWAY);
//
//            if ($game->getState() === State::Finished and is_object($externalSourceGame->homeScore)) {
//                $home = $externalSourceGame->homeScore->current;
//                $away = $externalSourceGame->awayScore->current;
//                new GameBase\Score($game, $home, $away, GameBase::PHASE_REGULARTIME);
//            }

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

    protected function getPlaceFromPoule(Poule $poule, Competitor $competitor): ?Place
    {
        // @TODO DEPRECATED
        return null;
//        $places = $poule->getPlaces()->filter(function (Place $place) use ($competitor): bool {
//            return $place->getCompetitor() !== null && $place->getCompetitor()->getId() === $competitor->getId();
//        });
//        if ($places->count() !== 1) {
//            return null;
//        }
//        return $places->first();
    }
}
