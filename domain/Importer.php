<?php

declare(strict_types=1);

namespace SportsImport;

use Psr\Log\LoggerInterface;
use Sports\Association;
use Sports\Competition;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Competitor\StartLocationMap;
use Sports\Competitor\Team as TeamCompetitorBase;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Game\State as GameState;
use Sports\League;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Person;
use Sports\Season;
use Sports\Sport;
use Sports\Team;
use Sports\Team as TeamBase;
use Sports\Team\Player;
use SportsHelpers\SportRange;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Game\Against\Repository as AgainstGameAttacherRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\ExternalSource\Competitions;
use SportsImport\ExternalSource\CompetitionStructure;
use SportsImport\ExternalSource\GamesAndPlayers;
use SportsImport\ExternalSource\Transfers;
use SportsImport\Queue\Game\ImportEvents as ImportGameEvents;
use SportsImport\Queue\Person\ImportEvents as ImportPersonEvents;

class Importer
{
    protected ImportGameEvents|null $importGameEventsSender = null;
    protected ImportPersonEvents|null $importPersonEventsSender = null;

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
        protected ImporterHelpers\Transfers $transfersImportService,
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

    public function setGameEventSender(ImportGameEvents $importGameEventsSender): void
    {
        $this->importGameEventsSender = $importGameEventsSender;
    }

    public function setPersonEventSender(ImportPersonEvents $importPersonEventsSender): void
    {
        $this->importPersonEventsSender = $importPersonEventsSender;
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
        $this->teamCompetitorImportService->import($externalSource, $competitors);
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


    public function importTeamTransfers(
        Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        Transfers $externalSourceTransfers,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season,
        Team $team
    ): void {
        $externalCompetition = $this->getter->getCompetition(
            $externalSourceCompetitions,
            $externalSource,
            $sport,
            $league,
            $season
        );

        $competition = $this->competitionRepos->findOneExt($league, $season);
        if ($competition === null) {
            $this->logger->error("no compettition could be found for external league and season");
            return;
        }
        if ($competition->getTeamCompetitors()->count() === 0) {
            $this->logger->warning("no competitors found for external competition " . $externalCompetition->getName());
        }

        $externalTeamId = $this->teamAttacherRepos->findExternalId($externalSource, $team);
        if ($externalTeamId === null) {
            $this->logger->error(
                'no externalId found for team "' . $team->getName() . '" (' . (string)$team->getId() . ')'
            );
            return;
        }

        $externalTeam = $externalSourceCompetitionStructure->getTeam($externalCompetition, $externalTeamId);
        if ($externalTeam === null) {
            $this->logger->error('no external team found for externalId "' . $externalTeamId . '"');
            return;
        }

        $externalTransfers = $externalSourceTransfers->getTransfers($competition, $externalTeam);
        if ($this->importPersonEventsSender instanceof ImportPersonEvents) {
            $this->personImportService->setEventSender($this->importPersonEventsSender);
        }
        foreach ($externalTransfers as $externalTransfer) {
            $this->personImportService->importPerson($externalSource, $externalTransfer->getPerson(), $season);
        }

        $this->transfersImportService->import($externalSource, $competition, $externalTransfers);
    }

    public function importGamesBasics(
        Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        GamesAndPlayers $externalSourceGamesAndPlayers,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season,
        bool $resetCache,
        SportRange|null $gameRoundRange = null
    ): void {
        $this->importGamesHelper(
            $externalSourceCompetitions,
            $externalSourceCompetitionStructure,
            $externalSourceGamesAndPlayers,
            $externalSource,
            $sport,
            $league,
            $season,
            true,
            $resetCache,
            $gameRoundRange
        );
    }

    public function importGamesComplete(
        Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        GamesAndPlayers $externalSourceGamesAndPlayers,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season,
        bool $resetCache,
        SportRange|null $gameRoundRange = null
    ): void {
        $this->importGamesHelper(
            $externalSourceCompetitions,
            $externalSourceCompetitionStructure,
            $externalSourceGamesAndPlayers,
            $externalSource,
            $sport,
            $league,
            $season,
            false,
            $resetCache,
            $gameRoundRange
        );
    }

    protected function importGamesHelper(
        Competitions $externalSourceCompetitions,
        CompetitionStructure $externalSourceCompetitionStructure,
        GamesAndPlayers $externalSourceGamesAndPlayers,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season,
        bool $onlyBasics,
        bool $resetCache,
        SportRange|null $gameRoundRange
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
        if ($this->importGameEventsSender instanceof ImportGameEvents) {
            $this->againstGameImportService->setEventSender($this->importGameEventsSender);
        }
        $competition = $this->competitionRepos->findOneExt($league, $season);
        if ($competition === null) {
            $this->logger->error("no compettition could be found for external league and season");
            return;
        }
        if ($competition->getTeamCompetitors()->count() === 0) {
            $this->logger->warning("no competitors found for external competition " . $externalCompetition->getName());
        }

        $gameRoundNumbers = $externalSourceGamesAndPlayers->getGameRoundNumbers($externalCompetition);
        if ($gameRoundRange !== null) {
            $gameRoundNumbers = array_filter($gameRoundNumbers, fn (int $number) => $gameRoundRange->isWithIn($number));
        } else {
            $gameRoundNumbers = $this->getGameRoundNumbersToImport($competition, $nrOfPlaces, $gameRoundNumbers);
        }
        foreach ($gameRoundNumbers as $gameRoundNumber) {
            if ($onlyBasics) {
                $externalGames = $externalSourceGamesAndPlayers->getAgainstGamesBasics(
                    $externalCompetition,
                    $gameRoundNumber
                );
            } else {
                $externalGames = $externalSourceGamesAndPlayers->getAgainstGamesComplete(
                    $externalCompetition,
                    $gameRoundNumber,
                    $resetCache
                );
                foreach ($externalGames as $externalGame) {
                    $this->personImportService->importByAgainstGame($externalSource, $season, $externalGame);
                }
            }
            $this->againstGameImportService->importGames($externalSource, array_values($externalGames), $onlyBasics);
        }
    }

    public function importAgainstGameBasicsLineupsAndEvents(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource\CompetitionStructure $externalSourceCompetitionStructure,
        ExternalSource\GamesAndPlayers $externalSourceCompetitionGames,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season,
        string $externalGameId,
        bool $resetCache
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
            $this->logger->warning(
                "the competition could not be found for league " . $league->getName(
                ) . " and season " . $season->getName()
            );
            return;
        }
        if ($competition->getTeamCompetitors()->count() === 0) {
            $this->logger->warning("no competitors found for external competition " . $externalCompetition->getName());
        }


        if ($this->importGameEventsSender instanceof ImportGameEvents) {
            $this->againstGameImportService->setEventSender($this->importGameEventsSender);
        }
        try {
            $externalGame = $this->getter->getAgainstGame(
                $externalSourceCompetitionGames,
                $externalSource,
                $externalCompetition,
                $externalGameId,
                $resetCache
            );
            if ($externalGame->getState() !== GameState::Finished) {
                $this->logger->info("game " . (string)$externalGame->getId() . " is not finished");
                return;
            }
            $this->personImportService->importByAgainstGame(
                $externalSource,
                $season,
                $externalGame
            );

            $this->againstGameImportService->importBasics($externalSource, $externalGame);

            $this->againstGameImportService->importScoresLineupsAndEvents(
                $externalSource,
                $externalGame
            );
        } catch (\Exception $e) {
            $game = $this->againstGameAttacherRepos->findImportable($externalSource, $externalGameId);
            if ($game !== null) {
                // all batch should be stopped, because of editing playerperiods
                $competitors = array_values($competition->getTeamCompetitors()->toArray());
                $gameOutput = new AgainstGameOutput(new StartLocationMap($competitors), $this->logger);
                $gameOutput->output($game, $e->getMessage());
            }
        }
    }

    public function importPlayerImages(
        ExternalSource\GamesAndPlayers $externalSourceGamesAndPlayers,
        ExternalSource $externalSource,
        League $league,
        Season $season,
        string $localOutputPath,
        Person|null $person = null
    ): void {
        $competition = $league->getCompetition($season);
        if ($competition === null) {
            return;
        }
//        $nrUpdated = 0;
//        $maxUpdated = 20;

        if ($person !== null) {
            $personExternalId = $this->personAttacherRepos->findExternalId(
                $externalSource,
                $person
            );
            if ($personExternalId === null) {
                return;
            }
        }

        $teams = array_map(function (TeamCompetitorBase $teamCompetitor): TeamBase {
            return $teamCompetitor->getTeam();
        }, $competition->getTeamCompetitors()->toArray());
        foreach ($teams as $team) {
            $activePlayers = array_filter(
                $team->getPlayers()->toArray(),
                function (Player $player) use ($season, $person): bool {
                    return $player->getEndDateTime() > $season->getStartDateTime()
                        && ($person === null || $person === $player->getPerson());
                }
            );
            foreach ($activePlayers as $activePlayer) {
                $this->playerImportService->importImage(
                    $externalSourceGamesAndPlayers,
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
        int $maxWidth,
        Team|null $teamFilter = null
    ): void {
        $competition = $league->getCompetition($season);
        if ($competition === null) {
            return;
        }

        $teams = $competition->getTeamCompetitors()->map(function (TeamCompetitorBase $teamCompetitor): TeamBase {
            return $teamCompetitor->getTeam();
        });
        foreach ($teams as $team) {
            if ($teamFilter !== null && $teamFilter !== $team) {
                continue;
            }
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
        array $gameRoundNumbers
    ): array {
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
                [GameState::Finished],
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
