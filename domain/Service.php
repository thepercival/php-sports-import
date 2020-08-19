<?php

namespace SportsImport;

use Psr\Log\LoggerInterface;

use Sports\Competition;
use Sports\League;
use Sports\Season;
use SportsImport\ExternalSource\Implementation;
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

    protected LeagueAttacherRepository $leagueAttacherRepos;
    protected SeasonAttacherRepository $seasonAttacherRepos;
    protected CompetitionAttacherRepository $competitionAttacherRepos;

    public function __construct(
        Service\Sport $sportImportService,
        Service\Association $associationImportService,
        Service\Season $seasonImportService,
        Service\League $leagueImportService,
        Service\Competition $competitionImportService,
        Service\Team $teamImportService,
        Service\TeamCompetitor $teamCompetitorImportService,
        LeagueAttacherRepository $leagueAttacherRepos,
        SeasonAttacherRepository $seasonAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos
    ) {
        $this->sportImportService = $sportImportService;
        $this->associationImportService = $associationImportService;
        $this->seasonImportService = $seasonImportService;
        $this->leagueImportService = $leagueImportService;
        $this->competitionImportService = $competitionImportService;
        $this->teamImportService = $teamImportService;
        $this->teamCompetitorImportService = $teamCompetitorImportService;
        $this->associationImportService = $associationImportService;
        $this->leagueAttacherRepos = $leagueAttacherRepos;
        $this->seasonAttacherRepos = $seasonAttacherRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
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

    public function importAssociations( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceAssociation)) {
            return;
        }
        $this->associationImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getAssociations()
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

    public function importLeagues( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceLeague)) {
            return;
        }
        $this->leagueImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getLeagues()
        );
    }

    public function importCompetition(
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetitionByLeagueAndSeason(
            $externalSourceImplementation, $league, $season );
        if( $externalCompetition === null ) {
            return;
        }
        $this->competitionImportService->import(
            $externalSourceImplementation->getExternalSource(), $externalCompetition
        );
    }

    public function getExternalCompetitionByLeagueAndSeason(
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season): ?Competition
    {
        if (!($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return null;
        }
        $leagueAttacher = $this->leagueAttacherRepos->findOneByImportable(
            $externalSourceImplementation->getExternalSource(), $league );
        if( $leagueAttacher === null ) {
            return null;
        }
        $seasonAttacher = $this->seasonAttacherRepos->findOneByImportable(
            $externalSourceImplementation->getExternalSource(), $season );
        if( $seasonAttacher === null ) {
            return null;
        }
        return $externalSourceImplementation->getCompetition(
            $leagueAttacher->getExternalId(), $seasonAttacher->getExternalId()
        );

    }

    public function importTeams(
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceTeam)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetitionByLeagueAndSeason(
            $externalSourceImplementation, $league, $season );
        if( $externalCompetition === null ) {
            return;
        }
        $this->teamImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getTeams($externalCompetition)
        );
    }

    public function importTeamCompetitors(
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceTeamCompetitor)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetitionByLeagueAndSeason(
            $externalSourceImplementation, $league, $season );
        if( $externalCompetition === null ) {
            return;
        }
        $this->teamCompetitorImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getTeamCompetitors($externalCompetition)
        );

    }

    public function importStructure(
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetitionByLeagueAndSeason(
            $externalSourceImplementation, $league, $season );
        if( $externalCompetition === null ) {
            return;
        }
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
        ExternalSourceImplementation $externalSourceImplementation, League $league, Season $season)
    {
        if (!($externalSourceImplementation instanceof ExternalSourceGame)
            || !($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $externalCompetition = $this->getExternalCompetitionByLeagueAndSeason(
            $externalSourceImplementation, $league, $season );
        if( $externalCompetition === null ) {
            return;
        }

        $nrOfPlaces = $externalSourceImplementation->getStructure($externalCompetition)->getFirstRoundNumber()->getNrOfPlaces();
        if ($nrOfPlaces === 0) {
            return;
        }
        $batchNrs = $externalSourceImplementation->getBatchNrs($externalCompetition, true);
        foreach ($batchNrs as $batchNr) {
            // always import, do single import for for example superelf
//                $finishedGames = $gameRepos->getCompetitionGames($competition, State::Finished, $batchNr);
//                if ((count($finishedGames) * 2) === $nrOfPlaces) {
//                    continue;
//                }
            // $importGameService->setPoule( );
            $this->gameImportService->import(
                $externalSourceImplementation->getExternalSource(),
                $externalSourceImplementation->getGames($externalCompetition, $batchNr)
            );
        }
    }

    // wedstrijden
    // events->rounds heeft het aantal ronden, dit is per competitie op te vragen
    // per wedstrijdronde de games invoeren, voor de ronden die nog niet ingevoerd zijn
    // 0 notstarted
    // 70 canceled
    // 100 finished

    // wedstrijden te updaten uit aparte url per wedstrijdronde
    // roundMatches->tournaments[]->events[]
}
