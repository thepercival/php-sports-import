<?php

namespace SportsImport;

use Psr\Log\LoggerInterface;

use Sports\Association;
use Sports\Competition;
use Sports\League;
use Sports\Season;
use Sports\Game;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Game\Repository as GameRepository;
use Sports\Sport;
use Sports\State;
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
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;

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

    protected SportAttacherRepository $sportAttacherRepos;
    protected AssociationAttacherRepository $associationAttacherRepos;
    protected LeagueAttacherRepository $leagueAttacherRepos;
    protected SeasonAttacherRepository $seasonAttacherRepos;
    protected CompetitionAttacherRepository $competitionAttacherRepos;

    protected CompetitionRepository $competitionRepos;
    protected GameRepository $gameRepos;

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
        SportAttacherRepository $sportAttacherRepos,
        AssociationAttacherRepository $associationAttacherRepos,
        LeagueAttacherRepository $leagueAttacherRepos,
        SeasonAttacherRepository $seasonAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        CompetitionRepository $competitionRepos,
        GameRepository $gameRepos
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
        $this->sportAttacherRepos = $sportAttacherRepos;
        $this->associationAttacherRepos = $associationAttacherRepos;
        $this->leagueAttacherRepos = $leagueAttacherRepos;
        $this->seasonAttacherRepos = $seasonAttacherRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
        $this->competitionRepos = $competitionRepos;
        $this->gameRepos = $gameRepos;
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
    public function importGames(
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
            return;
        }
        $batchNrs = $externalSourceImplementation->getBatchNrs($externalCompetition);
        $filteredBatchNrs = $this->getBatchNrsToImport($league, $season, $nrOfPlaces, $batchNrs);

        foreach ($filteredBatchNrs as $batchNr) {
            $this->gameImportService->import(
                $externalSourceImplementation->getExternalSource(),
                $externalSourceImplementation->getGames($externalCompetition, $batchNr)
            );
        }
    }

    /**
     * als batchNr is finished and more than 2 days old
     *
     * @param League $league
     * @param Season $season
     * @param int $nrOfPlaces
     * @param array|int[] $batchNrs
     * @return array|int[]
     */
    protected function getBatchNrsToImport( League $league, Season $season, int $nrOfPlaces, array $batchNrs ): array {
        $batchNrsRet = [];

        foreach( $batchNrs as $batchNr ) {
            $batchNrGamePlaces = $this->gameRepos->getNrOfCompetitionGamePlaces(
                $this->competitionRepos->findExt($league, $season),
                State::Finished,
                $batchNr );
            if( $batchNrGamePlaces >= ($nrOfPlaces-1) ) {
                continue;
            }
            $batchNrsRet[] = $batchNr;
            if( count( $batchNrsRet ) === 4 ) {
                break;
            }
        }
        return $batchNrsRet;
    }

    /**
     * imports only batches which are not finished
     *
     * @param ExternalSourceImplementation $externalSourceImplementation
     */
    public function importTeamRoles(
        ExternalSourceImplementation $externalSourceImplementation, Game $game )
    {
        if (!($externalSourceImplementation instanceof ExternalSourceGame)) {
            return;
        }

//      $game->g
//
//        foreach ($filteredBatchNrs as $batchNr) {
//            $this->gameImportService->import(
//                $externalSourceImplementation->getExternalSource(),
//                $externalSourceImplementation->getGames($externalCompetition, $batchNr)
//            );
//        }
    }
}
