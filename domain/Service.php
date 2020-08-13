<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 12-3-17
 * Time: 22:17
 */

namespace SportsImport;

use Psr\Log\LoggerInterface;

use SportsImport\Attacher\Game\Repository as GameAttacherRepository;
use SportsImport\Attacher\Place\Repository as PlaceAttacherRepository;
use SportsImport\Attacher\Poule\Repository as PouleAttacherRepository;
use SportsImport\ExternalSource\Implementation as ExternalSourceImplementation;
use Sports\Game\Repository as GameRepository;
use Sports\Game\Score\Repository as GameScoreRepository;
use Sports\Sport\Repository as SportRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use SportsImport\ExternalSource\Sport as ExternalSourceSport;
use Sports\Association\Repository as AssociationRepository;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\ExternalSource\Association as ExternalSourceAssociation;
use Sports\Season\Repository as SeasonRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use SportsImport\ExternalSource\Season as ExternalSourceSeason;
use Sports\League\Repository as LeagueRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\ExternalSource\League as ExternalSourceLeague;
use Sports\Competition\Repository as CompetitionRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\ExternalSource\Competition as ExternalSourceCompetition;
use Sports\Competitor\Repository as CompetitorRepository;
use SportsImport\Attacher\Competitor\Repository as CompetitorAttacherRepository;
use SportsImport\ExternalSource\Competitor as ExternalSourceCompetitor;
use Sports\State;
use Sports\Structure\Repository as StructureRepository;
use SportsImport\ExternalSource\Structure as ExternalSourceStructure;
use SportsImport\ExternalSource\Game as ExternalSourceGame;

class Service
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public const SPORT_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const ASSOCIATION_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const SEASON_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const LEAGUE_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const COMPETITION_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const COMPETITOR_CACHE_MINUTES = 1440 * 7; // 60 * 24
    public const GAME_CACHE_MINUTES = 10; // 60 * 24

    /**
     * Service constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function importSports(
        ExternalSourceImplementation $externalSourceImplementation,
        SportRepository $sportRepos,
        SportAttacherRepository $sportAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceSport)) {
            return;
        }
        $importSportService = new Service\Sport(
            $sportRepos,
            $sportAttacherRepos,
            $this->logger
        );
        $importSportService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getSports()
        );
    }

    public function importAssociations(
        ExternalSourceImplementation $externalSourceImplementation,
        AssociationRepository $associationRepos,
        AssociationAttacherRepository $associationAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceAssociation)) {
            return;
        }
        $importAssociationService = new Service\Association(
            $associationRepos,
            $associationAttacherRepos,
            $this->logger
        );
        $importAssociationService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getAssociations()
        );
    }

    public function importSeasons(
        ExternalSourceImplementation $externalSourceImplementation,
        SeasonRepository $seasonRepos,
        SeasonAttacherRepository $seasonAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceSeason)) {
            return;
        }
        $importSeasonService = new Service\Season(
            $seasonRepos,
            $seasonAttacherRepos,
            $this->logger
        );
        $importSeasonService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getSeasons()
        );
    }

    public function importLeagues(
        ExternalSourceImplementation $externalSourceImplementation,
        LeagueRepository $leagueRepos,
        LeagueAttacherRepository $leagueAttacherRepos,
        AssociationAttacherRepository $associationAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceLeague)) {
            return;
        }
        $importLeagueService = new Service\League(
            $leagueRepos,
            $leagueAttacherRepos,
            $associationAttacherRepos,
            $this->logger
        );
        $importLeagueService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getLeagues()
        );
    }

    public function importCompetitions(
        ExternalSourceImplementation $externalSourceImplementation,
        CompetitionRepository $competitionRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        LeagueAttacherRepository $leagueAttacherRepos,
        SeasonAttacherRepository $seasonAttacherRepos,
        SportAttacherRepository $sportAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $importCompetitionService = new Service\Competition(
            $competitionRepos,
            $competitionAttacherRepos,
            $leagueAttacherRepos,
            $seasonAttacherRepos,
            $sportAttacherRepos,
            $this->logger
        );
        $importCompetitionService->import(
            $externalSourceImplementation->getExternalSource(),
            $externalSourceImplementation->getCompetitions()
        );
    }

    public function importCompetitors(
        ExternalSourceImplementation $externalSourceImplementation,
        CompetitorRepository $competitorRepos,
        CompetitorAttacherRepository $competitorAttacherRepos,
        AssociationAttacherRepository $associationAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceCompetitor)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $importCompetitorService = new Service\TeamCompetitor(
            $competitorRepos,
            $competitorAttacherRepos,
            $associationAttacherRepos,
            $this->logger
        );

        $filter = ["externalSource" => $externalSourceImplementation->getExternalSource() ];
        $competitionAttachers = $competitionAttacherRepos->findBy($filter);
        foreach ($competitionAttachers as $competitionAttacher) {
            $competition = $externalSourceImplementation->getCompetition($competitionAttacher->getExternalId());
            if ($competition === null) {
                continue;
            }
            $importCompetitorService->import(
                $externalSourceImplementation->getExternalSource(),
                $externalSourceImplementation->getCompetitors($competition)
            );
        }
    }

    public function importStructures(
        ExternalSourceImplementation $externalSourceImplementation,
        StructureRepository $structureRepos,
        CompetitorAttacherRepository $competitorAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $importStructureService = new Service\Structure(
            $structureRepos,
            $competitorAttacherRepos,
            $competitionAttacherRepos,
            $this->logger
        );

        $filter = ["externalSource" => $externalSourceImplementation->getExternalSource() ];
        $competitionAttachers = $competitionAttacherRepos->findBy($filter);
        foreach ($competitionAttachers as $competitionAttacher) {
            $competition = $externalSourceImplementation->getCompetition($competitionAttacher->getExternalId());
            if ($competition === null) {
                continue;
            }
            $importStructureService->import(
                $externalSourceImplementation->getExternalSource(),
                [$externalSourceImplementation->getStructure($competition)]
            );
        }
    }


    public function importGames(
        ExternalSourceImplementation $externalSourceImplementation,
        GameRepository $gameRepos,
        GameScoreRepository $gameScoreRepos,
        CompetitorRepository $competitorRepos,
        StructureRepository $structureRepos,
        GameAttacherRepository $gameAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        CompetitorAttacherRepository $competitorAttacherRepos
    ) {
        if (!($externalSourceImplementation instanceof ExternalSourceGame)
            || !($externalSourceImplementation instanceof ExternalSourceStructure)
            || !($externalSourceImplementation instanceof ExternalSourceCompetition)) {
            return;
        }
        $importGameService = new Service\Game(
            $gameRepos,
            $gameScoreRepos,
            $structureRepos,
            $gameAttacherRepos,
            $competitionAttacherRepos,
            $competitorAttacherRepos,
            $this->logger
        );

        $filter = ["externalSource" => $externalSourceImplementation->getExternalSource() ];
        $competitionAttachers = $competitionAttacherRepos->findBy($filter);
        foreach ($competitionAttachers as $competitionAttacher) {
            $externalCompetition = $externalSourceImplementation->getCompetition($competitionAttacher->getExternalId());
            if ($externalCompetition === null) {
                continue;
            }
            $competition = $competitionAttacher->getImportable();
            $nrOfCompetitors = $competitorRepos->getNrOfCompetitors($competition);
            if ($nrOfCompetitors === 0) {
                continue;
            }
            $batchNrs = $externalSourceImplementation->getBatchNrs($externalCompetition, true);
            foreach ($batchNrs as $batchNr) {
                $finishedGames = $gameRepos->getCompetitionGames($competition, State::Finished, $batchNr);
                if ((count($finishedGames) * 2) === $nrOfCompetitors) {
                    continue;
                }
                // $importGameService->setPoule( );
                $importGameService->import(
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
