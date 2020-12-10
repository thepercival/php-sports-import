<?php

namespace SportsImport;

use App\QueueService;
use Doctrine\Common\Collections\Collection;
use League\Period\Period;
use Psr\Log\LoggerInterface;

use Sports\Association;
use Sports\Competition;
use Sports\Team as TeamBase;
use Sports\Place\Location\Map as PlaceLocationMap;
use Sports\League;
use Sports\Output\Game as GameOutput;
use Sports\Season;
use Sports\Game;
use Sports\Competitor\Team as TeamCompetitorBase;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Game\Repository as GameRepository;
use Sports\Sport;
use Sports\State;
use Sports\Team\Player;
use SportsImport\ExternalSource\Implementation as ExternalSourceImplementation;
use SportsImport\ExternalSource\Sport as ExternalSourceSport;
use SportsImport\ExternalSource\Association as ExternalSourceAssociation;
use SportsImport\ExternalSource\Season as ExternalSourceSeason;
use SportsImport\ExternalSource\League as ExternalSourceLeague;
use SportsImport\ExternalSource\Competition as ExternalSourceCompetition;
use SportsImport\ExternalSource\Team as ExternalSourceTeam;
use SportsImport\ExternalSource\Competitor\Team as ExternalSourceTeamCompetitor;
use SportsImport\ExternalSource\Structure as ExternalSourceStructure;
use SportsImport\ExternalSource\Game as ExternalSourceGame;
use SportsImport\ExternalSource\Person as ExternalSourcePerson;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Game\Repository as GameAttacherRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\Queue\Game\ImportEvent as ImportGameEvent;
use SportsImport\Queue\Game\ImportDetailsEvent as ImportGameDetailsEvent;

class Service
{
    protected Service\Sport$sportImportService;
    protected Service\Association$associationImportService;
    protected Service\Season $seasonImportService;
    protected Service\League $leagueImportService;
    protected Service\Competition $competitionImportService;
    protected Service\Team $teamImportService;
    protected Service\TeamCompetitor $teamCompetitorImportService;
    protected Service\Structure $structureImportService;
    protected Service\Game $gameImportService;
    protected Service\Person $personImportService;

    protected SportAttacherRepository $sportAttacherRepos;
    protected AssociationAttacherRepository $associationAttacherRepos;
    protected LeagueAttacherRepository $leagueAttacherRepos;
    protected SeasonAttacherRepository $seasonAttacherRepos;
    protected CompetitionAttacherRepository $competitionAttacherRepos;
    protected GameAttacherRepository $gameAttacherRepos;
    protected PersonAttacherRepository $personAttacherRepos;
    protected TeamAttacherRepository $teamAttacherRepos;

    /**
     * @var ImportGameEvent | ImportGameDetailsEvent | null
     */
    protected $eventSender;

    protected CompetitionRepository $competitionRepos;
    protected GameRepository $gameRepos;
    protected LoggerInterface $logger;

    public function __construct(
        Service\Sport $sportImportService,
        Service\Association $associationImportService,
        Service\Season $seasonImportService,
        Service\League $leagueImportService,
        Service\Competition $competitionImportService,
        Service\Team $teamImportService,
        Service\TeamCompetitor $teamCompetitorImportService,
        Service\Structure $structureImportService,
        Service\Game $gameImportService,
        Service\Person $personImportService,
        SportAttacherRepository $sportAttacherRepos,
        AssociationAttacherRepository $associationAttacherRepos,
        LeagueAttacherRepository $leagueAttacherRepos,
        SeasonAttacherRepository $seasonAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        GameAttacherRepository $gameAttacherRepos,
        PersonAttacherRepository $personAttacherRepos,
        TeamAttacherRepository $teamAttacherRepos,
        CompetitionRepository $competitionRepos,
        GameRepository $gameRepos,
        LoggerInterface $logger
    ) {
        $this->sportImportService = $sportImportService;
        $this->associationImportService = $associationImportService;
        $this->seasonImportService = $seasonImportService;
        $this->leagueImportService = $leagueImportService;
        $this->competitionImportService = $competitionImportService;
        $this->teamImportService = $teamImportService;
        $this->teamCompetitorImportService = $teamCompetitorImportService;
        $this->structureImportService = $structureImportService;
        $this->gameImportService = $gameImportService;
        $this->personImportService = $personImportService;
        $this->sportAttacherRepos = $sportAttacherRepos;
        $this->associationAttacherRepos = $associationAttacherRepos;
        $this->leagueAttacherRepos = $leagueAttacherRepos;
        $this->seasonAttacherRepos = $seasonAttacherRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
        $this->gameAttacherRepos = $gameAttacherRepos;
        $this->personAttacherRepos = $personAttacherRepos;
        $this->teamAttacherRepos = $teamAttacherRepos;
        $this->competitionRepos = $competitionRepos;
        $this->gameRepos = $gameRepos;
        $this->logger = $logger;
    }

    /**
     * @param ImportGameEvent | ImportGameDetailsEvent $eventSender
     */
    public function setEventSender( $eventSender ) {
        $this->eventSender = $eventSender;
    }

    public function importSports( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceSport)) {
            return;
        }
        $this->sportImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getSports()
        );
    }

    public function importAssociations( ExternalSourceImplementation $externalSourceImplementation, Sport $sport ) {
        if (!($externalSourceImplementation instanceof ExternalSourceAssociation)) {
            return;
        }
        $externalSport = $this->getExternalSport( $externalSourceImplementation, $sport );

        $this->associationImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getAssociations( $externalSport )
        );
    }

    public function importSeasons( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceSeason)) {
            return;
        }
        $this->seasonImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getSeasons()
        );
    }

    public function importLeagues( ExternalSourceImplementation $externalSourceImplementation, Association $association ) {
        if (!($externalSourceImplementation instanceof ExternalSourceLeague)) {
            return;
        }
        $this->leagueImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getLeagues( $association )
        );
    }

    public function importCompetition(
        ExternalSourceImplementation $externalSourceImplementation,
        Sport $sport, Association $association, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetition(
            $externalSourceImplementation, $sport, $association, $league, $season );

        $this->competitionImportService->import(
            $externalSourceImplementation->getExternalSource(), $externalCompetition
        );
    }

    public function getExternalSport(
        ExternalSourceImplementation $externalSourceImplementation, Sport $sport ): Sport
    {
        if (!($externalSourceImplementation instanceof ExternalSourceSport)) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" does not implement sports" , E_ERROR );
        }
        $sportAttacher = $this->sportAttacherRepos->findOneByImportable(
            $externalSourceImplementation->getExternalSource(), $sport );
        if( $sportAttacher === null ) {
            throw new \Exception("for external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" and sport \"" . $sport->getName() . "\" there is no externalId" , E_ERROR );
        }
        $externalSport = $externalSourceImplementation->getSport( $sportAttacher->getExternalId() );
        if( $externalSport === null ) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" could not find a sport for externalId \"" . $sportAttacher->getExternalId() . "\"" , E_ERROR );
        }
        return $externalSport;
    }

    public function getExternalGame(
        ExternalSourceImplementation $externalSourceImplementation,
        Competition $externalCompetition,
        Game $game ): Game
    {

        if (!($externalSourceImplementation instanceof ExternalSourceGame)) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" does not implement games" , E_ERROR );
        }
        $gameAttacher = $this->gameAttacherRepos->findOneByImportable(
            $externalSourceImplementation->getExternalSource(), $game );
        if( $gameAttacher === null ) {
            $competition = $game->getPoule()->getRound()->getNumber()->getCompetition();
            $placeLocationMap = new PlaceLocationMap( $competition->getTeamCompetitors()->toArray() );
            $gameOutput = new GameOutput( $placeLocationMap, $this->logger);
            $gameOutput->output( $game, "there is no externalId for external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" and game");
            throw new \Exception("there is no externalId for external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" and external gameid \"" . $game->getId() . "\"" , E_ERROR );
        }
        $externalGame = $externalSourceImplementation->getGame( $externalCompetition, $gameAttacher->getExternalId() );
        if( $externalGame === null ) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" could not find a game for externalId \"" . $gameAttacher->getExternalId() . "\"" , E_ERROR );
        }
        return $externalGame;
    }

    public function getExternalSeason(
        ExternalSourceImplementation $externalSourceImplementation, Season $season ): Season
    {
        if (!($externalSourceImplementation instanceof ExternalSourceSeason)) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" does not implement seasons" , E_ERROR );
        }
        $seasonAttacher = $this->seasonAttacherRepos->findOneByImportable(
            $externalSourceImplementation->getExternalSource(), $season );
        if( $seasonAttacher === null ) {
            throw new \Exception("for external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" and season \"" . $season->getName() . "\" there is no externalId" , E_ERROR );
        }
        $externalSeason = $externalSourceImplementation->getSeason( $seasonAttacher->getExternalId() );
        if( $externalSeason === null ) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" could not find a season for externalId \"" . $seasonAttacher->getExternalId() . "\"" , E_ERROR );
        }
        return $externalSeason;
    }

    public function getExternalAssociation(
        ExternalSourceImplementation $externalSourceImplementation, Sport $sport, Association $association): Association
    {
        if (!($externalSourceImplementation instanceof ExternalSourceAssociation)) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" does not implement associations" , E_ERROR );
        }
        $externalSport = $this->getExternalSport( $externalSourceImplementation, $sport );

        $associationAttacher = $this->associationAttacherRepos->findOneByImportable(
            $externalSourceImplementation->getExternalSource(), $association
        );
        if( $associationAttacher === null ) {
            throw new \Exception("for external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" and association \"" . $association->getName() . "\" there is no externalId" , E_ERROR );
        }
        $externalAssociation = $externalSourceImplementation->getAssociation( $externalSport, $associationAttacher->getExternalId() );
        if( $externalAssociation === null ) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" could not find an externalId for \"" . $associationAttacher->getExternalId() . "\"" , E_ERROR );
        }
        return $externalAssociation;
    }

    public function getExternalLeague(
        ExternalSourceImplementation $externalSourceImplementation,
        Sport $sport, Association $association, League $league): League
    {
        if (!($externalSourceImplementation instanceof ExternalSourceLeague)) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" does not implement leagues" , E_ERROR );
        }
        $externalAssociation = $this->getExternalAssociation( $externalSourceImplementation, $sport, $association );

        $leagueAttacher = $this->leagueAttacherRepos->findOneByImportable(
            $externalSourceImplementation->getExternalSource(), $league );
        if( $leagueAttacher === null ) {
            throw new \Exception("for external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" and league \"" . $league->getName() . "\" there is no externalId" , E_ERROR );
        }
        $externalLeague = $externalSourceImplementation->getLeague( $externalAssociation, $leagueAttacher->getExternalId() );
        if( $externalLeague === null ) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" could not find a league for externalId \"" . $leagueAttacher->getExternalId() . "\"" , E_ERROR );
        }
        return $externalLeague;
    }

    public function getExternalCompetition(
        ExternalSourceImplementation $externalSourceImplementation,
        Sport $sport, Association $association, League $league, Season $season): Competition
    {
        if (!($externalSourceImplementation instanceof ExternalSourceSeason)) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" does not implement seasons" , E_ERROR );
        }
        if (!($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" does not implement competitions" , E_ERROR );
        }
        $externalSport = $this->getExternalSport( $externalSourceImplementation, $sport );
        $externalLeague = $this->getExternalLeague( $externalSourceImplementation, $sport, $association, $league );
        $externalSeason = $this->getExternalSeason( $externalSourceImplementation, $season );

        $externalCompetition = $externalSourceImplementation->getCompetition(
            $externalSport, $externalLeague, $externalSeason
        );
        if( $externalCompetition === null ) {
            throw new \Exception("external source \"" . $externalSourceImplementation->getExternalSource()->getName() ."\" could not find a competition for sport/league/season \"" . $externalSport->getName() . "\"/\"" . $externalLeague->getName() . "\"/\"" . $externalSeason->getName() . "\"" , E_ERROR );
        }
        return $externalCompetition;
    }

    public function importTeams(
        ExternalSourceImplementation $externalSourceImplementation
        , Sport $sport, Association $association, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceTeam)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetition(
            $externalSourceImplementation, $sport, $association, $league, $season );

        $this->teamImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getTeams($externalCompetition)
        );
    }

    public function importTeamCompetitors(
        ExternalSourceImplementation $externalSourceImplementation
        , Sport $sport, Association $association, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceTeamCompetitor)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetition(
            $externalSourceImplementation, $sport, $association, $league, $season );

        $this->teamCompetitorImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getTeamCompetitors($externalCompetition)
        );

    }

    public function importStructure(
        ExternalSourceImplementation $externalSourceImplementation,
        Sport $sport, Association $association, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetition(
            $externalSourceImplementation, $sport, $association, $league, $season );

        $this->structureImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getStructure($externalCompetition)
        );
    }


    /**
     * imports only batches which are not finished
     *
     * @param ExternalSourceImplementation $externalSourceImplementation
     */
    public function importSchedule(
        ExternalSourceImplementation $externalSourceImplementation,
        Sport $sport, Association $association, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceGame)
            || !($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetition(
            $externalSourceImplementation, $sport, $association, $league, $season );

        $nrOfPlaces = $externalSourceImplementation->getStructure($externalCompetition)->getFirstRoundNumber()->getNrOfPlaces();
        if ($nrOfPlaces === 0) {
            $this->logger->warning("no structure found for external competition " . $externalCompetition->getName() );
            return;
        }
        if( $this->eventSender !== null ) {
            if( $this->eventSender instanceof ImportGameEvent ) {
                $this->gameImportService->setEventSender( $this->eventSender );
            }
        }

        $competition = $this->competitionRepos->findOneExt($league, $season);
        if ($competition->getTeamCompetitors()->count() === 0) {
            $this->logger->warning("no competitors found for external competition " . $externalCompetition->getName() );
        }

        $batchNrs = $externalSourceImplementation->getBatchNrs($externalCompetition);
        $filteredBatchNrs = $this->getBatchNrsToImport($competition, $nrOfPlaces, $batchNrs);

        foreach ($filteredBatchNrs as $batchNr) {
            $externalGames = $externalSourceImplementation->getGames($externalCompetition, $batchNr);

            $this->gameImportService->importSchedule(
                $externalSourceImplementation->getExternalSource(),
                $externalGames
            );
        }
    }

    /**
     * imports only batches which are not finished
     *
     * @param ExternalSourceImplementation $externalSourceImplementation
     */
    public function importGameDetails(
        ExternalSourceImplementation $externalSourceImplementation,
        Sport $sport, Association $association, League $league, Season $season, Period $period)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceGame)
            || !($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetition(
            $externalSourceImplementation, $sport, $association, $league, $season );

        $nrOfPlaces = $externalSourceImplementation->getStructure($externalCompetition)->getFirstRoundNumber()->getNrOfPlaces();
        if ($nrOfPlaces === 0) {
            $this->logger->warning("no structure found for external competition " . $externalCompetition->getName() );
            return;
        }

        $competition = $this->competitionRepos->findOneExt($league, $season);
        if ($competition->getTeamCompetitors()->count() === 0) {
            $this->logger->warning("no competitors found for external competition " . $externalCompetition->getName() );
        }
        $games = $this->gameRepos->getCompetitionGames( $competition, null,null, $period );
        if( $this->eventSender !== null ) {
            if( $this->eventSender instanceof ImportGameDetailsEvent ) {
                $this->gameImportService->setEventSender( $this->eventSender );
            }
        }
        $game = null;
        try {
            foreach ($games as $game) {
                $externalGame = $this->getExternalGame( $externalSourceImplementation, $externalCompetition, $game );
                if( $externalGame->getState() !== State::Finished ) {
                    $this->logger->info("game " . $externalGame->getId() . " is not finished");
                    continue;
                }
                $this->personImportService->importByGame(
                    $externalSourceImplementation->getExternalSource(),
                    $externalGame
                );

                $this->gameImportService->importDetails(
                    $externalSourceImplementation->getExternalSource(),
                    $externalGame
                );
            }
        } catch( \Exception $e ) {
            // all batch should be stopped, because of editing playerperiods
            $placeLocationMap = new PlaceLocationMap( $competition->getTeamCompetitors()->toArray() );
            $gameOutput = new GameOutput( $placeLocationMap, $this->logger);
            $gameOutput->output( $game, $e->getMessage() );
        }
    }

    public function importPersonImages(
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season,
        string $localOutputPath, string $publicOutputPath, int $maxWidth)
    {
        if (!($externalSourceImplementation instanceof ExternalSourcePerson)
        || !($externalSourceImplementation instanceof ExternalSourceTeam)) {
            return;
        }
        $competition = $league->getCompetition( $season );
        $nrUpdated = 0; $maxUpdated = 10;

        $teams = $competition->getTeamCompetitors()->map( function(TeamCompetitorBase $teamCompetitor): TeamBase {
            return $teamCompetitor->getTeam();
        });
        foreach( $teams as $team ) {
            $activePlayers = $team->getPlayers()->filter( function( Player $player ) use ($season): bool {
                return $player->getEndDateTime() > $season->getStartDateTime();
            } );
            foreach( $activePlayers as $activePlayer ) {
                $person = $activePlayer->getPerson();
                $personExternalId = $this->personAttacherRepos->findExternalId(
                    $externalSourceImplementation->getExternalSource(), $person );
                if( $personExternalId === null ) {
                    continue;
                }
                if( !$this->personImportService->importImage(
                    $externalSourceImplementation, $externalSourceImplementation->getExternalSource(),
                    $person, $localOutputPath, $publicOutputPath, $maxWidth
                ) ) {
                    continue;
                }
                if( ++$nrUpdated === $maxUpdated ) {
                    return;
                }
            }
        }
    }

    public function importTeamImages(
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season,
        string $localOutputPath, string $publicOutputPath, int $maxWidth)
    {
        if (!($externalSourceImplementation instanceof ExternalSourcePerson)
            || !($externalSourceImplementation instanceof ExternalSourceTeam)) {
            return;
        }
        $competition = $league->getCompetition( $season );
        $nrUpdated = 0; $maxUpdated = 10;

        $teams = $competition->getTeamCompetitors()->map( function(TeamCompetitorBase $teamCompetitor): TeamBase {
            return $teamCompetitor->getTeam();
        });
        foreach( $teams as $team ) {
            $teamExternalId = $this->teamAttacherRepos->findExternalId(
                $externalSourceImplementation->getExternalSource(), $team );
            if( $teamExternalId === null ) {
                continue;
            }
            if( !$this->teamImportService->importImage(
                $externalSourceImplementation, $externalSourceImplementation->getExternalSource(),
                $team, $localOutputPath, $publicOutputPath, $maxWidth
            ) ) {
                continue;
            }
            if( ++$nrUpdated === $maxUpdated ) {
                return;
            }
        }
    }

    /**
     * als batchNr is finished and more than 2 days old
     *
     * @param Competition $competition
     * @param int $nrOfPlaces
     * @param array|int[] $batchNrs
     * @return array|int[]
     */
    protected function getBatchNrsToImport( Competition $competition, int $nrOfPlaces, array $batchNrs ): array {
        $batchNrsRet = [];

        foreach( $batchNrs as $batchNr ) {
            $hasBatchNrGames = $this->gameRepos->hasCompetitionGames(
                $competition, null, $batchNr );
            if( $hasBatchNrGames ) {
                continue;
            }
            $batchNrsRet[] = $batchNr;
            if( count( $batchNrsRet ) === 4 ) {
                return $batchNrsRet;
            }
        }

        foreach( $batchNrs as $batchNr ) {
            $batchNrGamePlaces = $this->gameRepos->getNrOfCompetitionGamePlaces(
                $competition,
                State::Finished,
                $batchNr );
            if( $batchNrGamePlaces >= ($nrOfPlaces-1) ) {
                continue;
            }
            $batchNrsRet[] = $batchNr;
            if( count( $batchNrsRet ) === 4 ) {
                return $batchNrsRet;
            }
        }
        return $batchNrsRet;
    }
}
