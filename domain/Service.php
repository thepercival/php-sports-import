<?php

namespace SportsImport;

use Psr\Log\LoggerInterface;

use SportsImport\ExternalSource\Implementation as ExternalSourceImplementation;
use SportsImport\ExternalSource\Sport as ExternalSourceSport;
use SportsImport\ExternalSource\Association as ExternalSourceAssociation;
use SportsImport\ExternalSource\Season as ExternalSourceSeason;
use SportsImport\ExternalSource\League as ExternalSourceLeague;
use SportsImport\ExternalSource\Competition as ExternalSourceCompetition;
use SportsImport\ExternalSource\Team as ExternalSourceTeam;
use SportsImport\ExternalSource\Competitor\Team as ExternalSourceTeamCompetitor;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\ExternalSource\Structure as ExternalSourceStructure;
use SportsImport\ExternalSource\Game as ExternalSourceGame;


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

    protected CompetitionAttacherRepository $competitionAttacherRepos;

    public const SPORT_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const ASSOCIATION_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const SEASON_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const LEAGUE_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const COMPETITION_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const COMPETITOR_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const GAME_CACHE_MINUTES = 10; // 60 * 24

    public function __construct(
        Service\Sport $sportImportService,
        Service\Association $associationImportService,
        Service\Season $seasonImportService,
        Service\League $leagueImportService,
        Service\Competition $competitionImportService,
        Service\Team $teamImportService,
        Service\TeamCompetitor $teamCompetitorImportService
    ) {
        $this->sportImportService = $sportImportService;
        $this->associationImportService = $associationImportService;
        $this->seasonImportService = $seasonImportService;
        $this->leagueImportService = $leagueImportService;
        $this->competitionImportService = $competitionImportService;
        $this->teamImportService = $teamImportService;
        $this->teamCompetitorImportService = $teamCompetitorImportService;
        $this->associationImportService = $associationImportService;
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

    public function importCompetitions( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $this->competitionImportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getCompetitions()
        );
    }

    public function importTeams( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceTeam)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $filter = ["externalSource" => $externalSourceImplementation->getExternalSource() ];
        $competitionAttachers = $this->competitionAttacherRepos->findBy($filter);
        foreach ($competitionAttachers as $competitionAttacher) {
            $competition = $externalSourceImplementation->getCompetition($competitionAttacher->getExternalId());
            if ($competition === null) {
                continue;
            }
            $this->teamImportService->import(
                $externalSourceImplementation->getExternalSource(),
                $externalSourceImplementation->getTeams($competition)
            );
        }
    }

    public function importTeamCompetitors( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceTeamCompetitor)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }

        $filter = ["externalSource" => $externalSourceImplementation->getExternalSource() ];
        $competitionAttachers = $this->competitionAttacherRepos->findBy($filter);
        foreach ($competitionAttachers as $competitionAttacher) {
            $competition = $externalSourceImplementation->getCompetition($competitionAttacher->getExternalId());
            if ($competition === null) {
                continue;
            }
            $this->teamCompetitorImportService->import(
                $externalSourceImplementation->getExternalSource(),
                $externalSourceImplementation->getTeamCompetitors($competition)
            );
        }
    }

    public function importStructures( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $filter = ["externalSource" => $externalSourceImplementation->getExternalSource() ];
        $competitionAttachers = $this->competitionAttacherRepos->findBy($filter);
        foreach ($competitionAttachers as $competitionAttacher) {
            $competition = $externalSourceImplementation->getCompetition($competitionAttacher->getExternalId());
            if ($competition === null) {
                continue;
            }
            $this->structureImportService->import(
                $externalSourceImplementation->getExternalSource(),
                $externalSourceImplementation->getStructure($competition)
            );
        }
    }

    /**
     * imports only batches which are not finished
     *
     * @param ExternalSourceImplementation $externalSourceImplementation
     */
    public function importGames( ExternalSourceImplementation $externalSourceImplementation ) {
        if (!($externalSourceImplementation instanceof ExternalSourceGame)
            || !($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }

        $filter = ["externalSource" => $externalSourceImplementation->getExternalSource() ];
        $competitionAttachers = $this->competitionAttacherRepos->findBy($filter);
        foreach ($competitionAttachers as $competitionAttacher) {
            $externalCompetition = $externalSourceImplementation->getCompetition($competitionAttacher->getExternalId());
            if ($externalCompetition === null) {
                continue;
            }
            $competition = $competitionAttacher->getImportable();
            $nrOfPlaces = $externalSourceImplementation->getStructure($competition)->getFirstRoundNumber()->getNrOfPlaces();
            if ($nrOfPlaces === 0) {
                continue;
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
