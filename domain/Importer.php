<?php
declare(strict_types=1);

namespace SportsImport;

use Doctrine\Common\Collections\ArrayCollection;
use League\Period\Period;
use Psr\Log\LoggerInterface;

use Sports\Association;
use Sports\Competition;
use Sports\Team as TeamBase;
use Sports\Competitor\Map as CompetitorMap;
use Sports\League;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Season;
use Sports\Competitor\Team as TeamCompetitorBase;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Sport;
use Sports\State;
use Sports\Team\Player;
use SportsHelpers\SportRange;
use SportsImport\ExternalSource\CompetitionDetails;
use SportsImport\ExternalSource\Competitions;
use SportsImport\ExternalSource\CompetitionStructure;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Game\Against\Repository as AgainstGameAttacherRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\Queue\Game\ImportEvent as ImportGameEvent;
use SportsImport\Queue\Game\ImportDetailsEvent as ImportGameDetailsEvent;

use function Amp\Iterator\toArray;

class Importer
{
    protected ImportGameEvent|ImportGameDetailsEvent|null $eventSender = null;

    public function __construct(
        protected Getter $getter,
        protected ImporterHelpers\Sport $sportImportService,
        protected ImporterHelpers\Association $associationImportService,
        protected ImporterHelpers\Season $seasonImportService,
        protected ImporterHelpers\League $leagueImportService,
        protected ImporterHelpers\Competition $competitionImportService,
        protected ImporterHelpers\Team $teamImportService,
        protected ImporterHelpers\TeamCompetitor $teamCompetitorImportService,
        protected ImporterHelpers\Structure $structureImportService,
        protected ImporterHelpers\Game\Against $againstGameImportService,
        protected ImporterHelpers\Person $personImportService,
        protected ImporterHelpers\Player $playerImportService,
        protected SportAttacherRepository $sportAttacherRepos,
        protected AssociationAttacherRepository $associationAttacherRepos,
        protected LeagueAttacherRepository $leagueAttacherRepos,
        protected SeasonAttacherRepository $seasonAttacherRepos,
        protected CompetitionAttacherRepository $competitionAttacherRepos,
        protected AgainstGameAttacherRepository $againstGameAttacherRepos,
        protected PersonAttacherRepository $personAttacherRepos,
        protected TeamAttacherRepository $teamAttacherRepos,
        protected CompetitionRepository $competitionRepos,
        protected AgainstGameRepository $againstGameRepos,
        protected LoggerInterface $logger
    ) {
    }

    public function setEventSender(ImportGameEvent | ImportGameDetailsEvent $eventSender): void
    {
        $this->eventSender = $eventSender;
    }

    public function importSports(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource
    ): void {
        $externalSports = array_values($externalSourceCompetitions->getSports());
        $this->sportImportService->import($externalSource, $externalSports);
    }

    public function importAssociations(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource,
        Sport $sport
    ): void {
        $externalSport = $this->getter->getSport($externalSourceCompetitions, $externalSource, $sport);
        $this->associationImportService->import(
            $externalSource,
            array_values($externalSourceCompetitions->getAssociations($externalSport))
        );
    }

    public function importSeasons(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource,
    ): void {
        $this->seasonImportService->import(
            $externalSource,
            array_values($externalSourceCompetitions->getSeasons())
        );
    }

    public function importLeagues(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association
    ): void {
        $externalAssociation = $this->getter->getAssociation(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $association
        );
        $this->leagueImportService->import(
            $externalSource,
            array_values($externalSourceCompetitions->getLeagues($externalAssociation))
        );
    }

    public function importCompetition(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season
    ): void {
        $externalCompetition = $this->getter->getCompetition(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $league,
            $season
        );

        $this->competitionImportService->import(
            $externalSource,
            $externalCompetition
        );
    }

    public function importTeams(
        ExternalSource\Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season
    ): void {
        $externalCompetition = $this->getter->getCompetition(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $league,
            $season
        );
        $this->teamImportService->import(
            $externalSource,
            $externalSourceCompetitionStructure->getTeams($externalCompetition)
        );
    }

    public function importTeamCompetitors(
        Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season
    ): void {
        $externalCompetition = $this->getter->getCompetition(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $league,
            $season
        );
        $competitors = $externalSourceCompetitionStructure->getTeamCompetitors($externalCompetition);
        $this->teamCompetitorImportService->import($externalSource, array_values($competitors));
    }

    public function importStructure(
        Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season
    ): void {
        $externalCompetition = $this->getter->getCompetition(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $league,
            $season
        );
        $structure = $externalSourceCompetitionStructure->getStructure($externalCompetition);
        $this->structureImportService->import($externalSource, $structure);
    }

    public function importSchedule(
        Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        CompetitionDetails $externalSourceCompetitionDetails,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season,
        SportRange|null $gameRoundRange = null
    ): void {
        $externalCompetition = $this->getter->getCompetition(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $league,
            $season
        );
        $structure = $externalSourceCompetitionStructure->getStructure($externalCompetition);
        $nrOfPlaces = $structure->getFirstRoundNumber()->getNrOfPlaces();
        if ($nrOfPlaces === 0) {
            $this->logger->warning("no structure found for external competition " . $externalCompetition->getName());
            return;
        }
        if ($this->eventSender !== null) {
            if ($this->eventSender instanceof ImportGameEvent) {
                $this->againstGameImportService->setEventSender($this->eventSender);
            }
        }
        $competition = $this->competitionRepos->findOneExt($league, $season);
        if ($competition === null) {
            $this->logger->error("no compettition could be found for external league and season");
            return;
        }
        if ($competition->getTeamCompetitors()->count() === 0) {
            $this->logger->warning("no competitors found for external competition " . $externalCompetition->getName());
        }

        $gameRoundNumbers = $externalSourceCompetitionDetails->getGameRoundNumbers($externalCompetition);
        if( $gameRoundRange !== null ) {
            $gameRoundNumbers = array_filter($gameRoundNumbers, fn(int $number) => $gameRoundRange->isWithIn($number));
            $gameRoundNumbers = array_values($gameRoundNumbers);
        }
        $filteredGameRoundNumbers = $this->getGameRoundNumbersToImport($competition, $nrOfPlaces, $gameRoundNumbers);

        foreach ($filteredGameRoundNumbers as $gameRoundNumber) {
            $externalGames = $externalSourceCompetitionDetails->getAgainstGames($externalCompetition, $gameRoundNumber);

            $this->againstGameImportService->importSchedule(
                $externalSource,
                array_values($externalGames)
            );
        }
    }

    public function importAgainstGameDetails(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource\CompetitionStructure $externalSourceCompetitionStructure,
        ExternalSource\CompetitionDetails $externalSourceCompetitionDetails,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season,
        string $externalGameId
    ): void {
        $externalCompetition = $this->getter->getCompetition(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $league,
            $season
        );
        $structure = $externalSourceCompetitionStructure->getStructure($externalCompetition);
        $nrOfPlaces = $structure->getFirstRoundNumber()->getNrOfPlaces();
        if ($nrOfPlaces === 0) {
            $this->logger->warning("no structure found for external competition " . $externalCompetition->getName());
            return;
        }

        $competition = $this->competitionRepos->findOneExt($league, $season);
        if ($competition === null) {
            $this->logger->warning("the competition could not be found for league " . $league->getName() . " and season " . $season->getName());
            return;
        }
        if ($competition->getTeamCompetitors()->count() === 0) {
            $this->logger->warning("no competitors found for external competition " . $externalCompetition->getName());
        }


        if ($this->eventSender !== null) {
            if ($this->eventSender instanceof ImportGameDetailsEvent) {
                $this->againstGameImportService->setEventSender($this->eventSender);
            }
        }
        try {
            $externalGame = $this->getter->getAgainstGame($externalSourceCompetitionDetails, $externalSource, $externalCompetition, $externalGameId);
            if ($externalGame->getState() !== State::Finished) {
                $this->logger->info("game " . (string)$externalGame->getId() . " is not finished");
                return;
            }
            $this->personImportService->importByAgainstGame(
                $externalSource,
                $externalGame
            );

            $this->againstGameImportService->importDetails(
                $externalSource,
                $externalGame
            );
        } catch (\Exception $e) {
            $game = $this->againstGameAttacherRepos->findImportable($externalSource, $externalGameId);
            if ($game !== null) {
                // all batch should be stopped, because of editing playerperiods
                $competitors = array_values($competition->getTeamCompetitors()->toArray());
                $gameOutput = new AgainstGameOutput(new CompetitorMap($competitors), $this->logger);
                $gameOutput->output($game, $e->getMessage());
            }
        }
    }

    public function importPlayerImages(
        ExternalSource\CompetitionDetails $externalSourceCompetitionDetails,
        ExternalSource $externalSource,
        League $league,
        Season $season,
        string $localOutputPath
    ): void {
        $competition = $league->getCompetition($season);
        if ($competition === null) {
            return;
        }
//        $nrUpdated = 0;
//        $maxUpdated = 20;

        $teams = array_map( function (TeamCompetitorBase $teamCompetitor): TeamBase {
                return $teamCompetitor->getTeam();
            }, $competition->getTeamCompetitors()->toArray());
        foreach ($teams as $team) {
            $activePlayers = array_filter( $team->getPlayers()->toArray(), function (Player $player) use ($season): bool {
                return $player->getEndDateTime() > $season->getStartDateTime();
            });
            foreach ($activePlayers as $activePlayer) {

                $this->playerImportService->importImage(
                    $externalSourceCompetitionDetails,
                    $externalSource,
                    $activePlayer,
                    $localOutputPath
                );
            }
        }
    }

    public function importTeamImages(
        ExternalSource\CompetitionStructure $externalSourceCompetitionStructure,
        ExternalSource $externalSource,
        League $league,
        Season $season,
        string $localOutputPath,
        int $maxWidth
    ): void {
        $competition = $league->getCompetition($season);
        if ($competition === null) {
            return;
        }

        $teams = $competition->getTeamCompetitors()->map(function (TeamCompetitorBase $teamCompetitor): TeamBase {
            return $teamCompetitor->getTeam();
        });
        foreach ($teams as $team) {
            $teamExternalId = $this->teamAttacherRepos->findExternalId(
                $externalSource,
                $team
            );
            if ($teamExternalId === null) {
                continue;
            }
            $this->teamImportService->importTeamImage(
                $externalSourceCompetitionStructure,
                $externalSource,
                $team,
                $localOutputPath,
                $maxWidth
            );
        }
    }

    /**
     * als gameRoundNumber is finished and more than 2 days old
     *
     * @param Competition $competition
     * @param int $nrOfPlaces
     * @param list<int> $gameRoundNumbers
     * @return list<int>
     */
    protected function getGameRoundNumbersToImport(
        Competition $competition,
        int $nrOfPlaces,
        array $gameRoundNumbers): array
    {
        $gameRoundNumbersRet = [];

        foreach ($gameRoundNumbers as $gameRoundNumber) {
            $hasGameRoundNumberGames = $this->againstGameRepos->hasCompetitionGames(
                $competition,
                null,
                $gameRoundNumber
            );
            if ($hasGameRoundNumberGames) {
                continue;
            }
            $gameRoundNumbersRet[] = $gameRoundNumber;
            if (count($gameRoundNumbersRet) === 4) {
                return $gameRoundNumbersRet;
            }
        }

        foreach ($gameRoundNumbers as $gameRoundNumber) {
            $gameRoundGamePlaces = $this->againstGameRepos->getNrOfCompetitionGamePlaces(
                $competition,
                State::Finished,
                $gameRoundNumber
            );
            if ($gameRoundGamePlaces >= ($nrOfPlaces-1)) {
                continue;
            }
            $gameRoundNumbersRet[] = $gameRoundNumber;
            if (count($gameRoundNumbersRet) === 4) {
                return $gameRoundNumbersRet;
            }
        }
        return $gameRoundNumbersRet;
    }
}
