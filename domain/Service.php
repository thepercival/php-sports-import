<?php
declare(strict_types=1);

namespace SportsImport;

use Doctrine\Common\Collections\Collection;
use League\Period\Period;
use Psr\Log\LoggerInterface;

use Sports\Association;
use Sports\Competition;
use Sports\Team as TeamBase;
use Sports\Competitor\Map as CompetitorMap;
use Sports\League;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Season;
use Sports\Game\Against as AgainstGame;
use Sports\Competitor\Team as TeamCompetitorBase;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Game\Against\Repository as AgainstGameRepository;
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
use SportsImport\ExternalSource\Game\Against as ExternalSourceAgainstGame;
use SportsImport\ExternalSource\Person as ExternalSourcePerson;
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

class Service
{
    protected ImportGameEvent|ImportGameDetailsEvent|null $eventSender = null;

    public function __construct(
        protected Service\Sport $sportImportService,
        protected Service\Association $associationImportService,
        protected Service\Season $seasonImportService,
        protected Service\League $leagueImportService,
        protected Service\Competition $competitionImportService,
        protected Service\Team $teamImportService,
        protected Service\TeamCompetitor $teamCompetitorImportService,
        protected Service\Structure $structureImportService,
        protected Service\Game\Against $againstGameImportService,
        protected Service\Person $personImportService,
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
        ExternalSourceSport $externalSourceSport,
        ExternalSource $externalSource
    ): void {
        $externalSports = array_values($externalSourceSport->getSports());
        $this->sportImportService->import($externalSource, $externalSports);
    }

    public function importAssociations(
        ExternalSourceAssociation $externalSourceAssociation,
        ExternalSource $externalSource,
        Sport $sport
    ): void {
        $externalSport = null;
        if ($externalSourceAssociation instanceof ExternalSourceSport) {
            $externalSport = $this->getExternalSport($externalSourceAssociation, $externalSource, $sport);
        }
        if ($externalSport === null) {
            throw new \Exception("for external source \"" . $externalSource->getName() ."\" and sport \"" . $sport->getName() . "\" there is no external found", E_ERROR);
        }
        $this->associationImportService->import(
            $externalSource,
            array_values($externalSourceAssociation->getAssociations($externalSport))
        );
    }

    public function importSeasons(
        ExternalSourceSeason $externalSourceSeason,
        ExternalSource $externalSource,
    ): void {
        $this->seasonImportService->import(
            $externalSource,
            array_values($externalSourceSeason->getSeasons())
        );
    }

    public function importLeagues(
        ExternalSourceLeague $externalSourceLeague,
        ExternalSource $externalSource,
        Association $association
    ): void {
        $this->leagueImportService->import(
            $externalSource,
            array_values($externalSourceLeague->getLeagues($association))
        );
    }

    public function importCompetition(
        ExternalSourceCompetition $externalSourceCompetition,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league,
        Season $season
    ): void {
        $externalCompetition = $this->getExternalCompetition(
            $externalSourceCompetition,
            $externalSource,
            $sport,
            $association,
            $league,
            $season
        );

        $this->competitionImportService->import(
            $externalSource,
            $externalCompetition
        );
    }

    public function getExternalSport(
        ExternalSourceSport $externalSourceSport,
        ExternalSource $externalSource,
        Sport $sport
    ): Sport {
        $sportAttacher = $this->sportAttacherRepos->findOneByImportable(
            $externalSource,
            $sport
        );
        if ($sportAttacher === null) {
            throw new \Exception('for external source "' . $externalSource->getName() .'" and sport "' . $sport->getName() . '" there is no externalId', E_ERROR);
        }
        $externalSport = $externalSourceSport->getSport($sportAttacher->getExternalId());
        if ($externalSport === null) {
            throw new \Exception('external source "' . $externalSource->getName() .'" could not find a sport for externalId "' . $sportAttacher->getExternalId() . '"', E_ERROR);
        }
        return $externalSport;
    }

    public function getExternalAgainstGame(
        ExternalSourceAgainstGame $externalSourceAgainstGame,
        ExternalSource$externalSource,
        Competition $externalCompetition,
        AgainstGame $againstGame
    ): AgainstGame {
        $gameAttacher = $this->againstGameAttacherRepos->findOneByImportable(
            $externalSource,
            $againstGame
        );
        if ($gameAttacher === null) {
            $competition = $againstGame->getPoule()->getRound()->getNumber()->getCompetition();
            $competitors = array_values($competition->getTeamCompetitors()->toArray());
            $competitorMap = new CompetitorMap($competitors);
            $gameOutput = new AgainstGameOutput($competitorMap, $this->logger);
            $gameOutput->output($againstGame, 'there is no externalId for external source "' . $externalSource->getName() .' and game');
            throw new \Exception('there is no externalId for external source "' . $externalSource->getName() .'" and external gameid "' . (string)$againstGame->getId() . '"', E_ERROR);
        }
        $externalGame = $externalSourceAgainstGame->getAgainstGame($externalCompetition, $gameAttacher->getExternalId());
        if ($externalGame === null) {
            throw new \Exception("external source \"" . $externalSource->getName() ."\" could not find a game for externalId \"" . $gameAttacher->getExternalId() . "\"", E_ERROR);
        }
        return $externalGame;
    }

    public function getExternalSeason(
        ExternalSourceSeason $externalSourceSeason,
        ExternalSource $externalSource,
        Season $season
    ): Season {
        $seasonAttacher = $this->seasonAttacherRepos->findOneByImportable(
            $externalSource,
            $season
        );
        if ($seasonAttacher === null) {
            throw new \Exception("for external source \"" . $externalSource->getName() ."\" and season \"" . $season->getName() . "\" there is no externalId", E_ERROR);
        }
        $externalSeason = $externalSourceSeason->getSeason($seasonAttacher->getExternalId());
        if ($externalSeason === null) {
            throw new \Exception('external source "' . $externalSource->getName() . '" could not find a season for externalId "' . $seasonAttacher->getExternalId() . '"', E_ERROR);
        }
        return $externalSeason;
    }

    public function getExternalAssociation(
        ExternalSourceAssociation $externalSourceAssociation,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association
    ): Association {
        $externalSport = null;
        if ($externalSourceAssociation instanceof ExternalSourceSport) {
            $externalSport = $this->getExternalSport($externalSourceAssociation, $externalSource, $sport);
        }
        if ($externalSport === null) {
            throw new \Exception("for external source \"" . $externalSource->getName() ."\" and sport \"" . $sport->getName() . "\" there is no external found", E_ERROR);
        }

        $associationAttacher = $this->associationAttacherRepos->findOneByImportable(
            $externalSource,
            $association
        );
        if ($associationAttacher === null) {
            throw new \Exception("for external source \"" . $externalSource->getName() ."\" and association \"" . $association->getName() . "\" there is no externalId", E_ERROR);
        }
        $externalAssociation = $externalSourceAssociation->getAssociation($externalSport, $associationAttacher->getExternalId());
        if ($externalAssociation === null) {
            throw new \Exception("external source \"" . $externalSource->getName() ."\" could not find an externalId for \"" . $associationAttacher->getExternalId() . "\"", E_ERROR);
        }
        return $externalAssociation;
    }

    public function getExternalLeague(
        ExternalSourceLeague $externalSourceLeague,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league
    ): League {
        $externalAssociation = null;
        if ($externalSourceLeague instanceof ExternalSourceAssociation) {
            $externalAssociation = $this->getExternalAssociation($externalSourceLeague, $externalSource, $sport, $association);
        }
        if ($externalAssociation === null) {
            $this->logger->error("no external association could be found for association");
            throw new \Exception('external source "' . $externalSource->getName() .'" could not find an external association for association "' . $association->getName() . '"', E_ERROR);
        }

        $leagueAttacher = $this->leagueAttacherRepos->findOneByImportable(
            $externalSource,
            $league
        );
        if ($leagueAttacher === null) {
            throw new \Exception('for external source "' . $externalSource->getName() .'" and league "' . $league->getName() . '" there is no externalId', E_ERROR);
        }
        $externalLeague = $externalSourceLeague->getLeague($externalAssociation, $leagueAttacher->getExternalId());
        if ($externalLeague === null) {
            throw new \Exception('external source "' . $externalSource->getName() . '" could not find a league for externalId "' . $leagueAttacher->getExternalId() . '"', E_ERROR);
        }
        return $externalLeague;
    }

    public function getExternalCompetition(
        ExternalSourceCompetition $externalSourceCompetition,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league,
        Season $season
    ): Competition {
        $externalSport = null;
        if ($externalSourceCompetition instanceof ExternalSourceSport) {
            $externalSport = $this->getExternalSport($externalSourceCompetition, $externalSource, $sport);
        }
        if ($externalSport === null) {
            $this->logger->error("no external sport could be found for sport");
            throw new \Exception('external source "' . $externalSource->getName() .'" could not find an external sport for sport "' . $sport->getName() . '"', E_ERROR);
        }

        $externalLeague = null;
        if ($externalSourceCompetition instanceof ExternalSourceLeague) {
            $externalLeague = $this->getExternalLeague($externalSourceCompetition, $externalSource, $sport, $association, $league);
        }
        if ($externalLeague === null) {
            $this->logger->error("no league could be found for external league");
            throw new \Exception('external source "' . $externalSource->getName() .'" could not find an external league for league "' . $league->getName() . '"', E_ERROR);
        }

        $externalSeason = null;
        if ($externalSourceCompetition instanceof ExternalSourceSeason) {
            $externalSeason = $this->getExternalSeason($externalSourceCompetition, $externalSource, $season);
        }
        if ($externalSeason === null) {
            $this->logger->error("no season could be found for external season");
            throw new \Exception('external source "' . $externalSource->getName() .'" could not find an external season for season "' . $season->getName() . '"', E_ERROR);
        }

        $externalCompetition = $externalSourceCompetition->getCompetition(
            $externalSport,
            $externalLeague,
            $externalSeason
        );
        if ($externalCompetition === null) {
            throw new \Exception("external source \"" . $externalSource->getName() ."\" could not find a competition for sport/league/season \"" . $externalSport->getName() . "\"/\"" . $externalLeague->getName() . "\"/\"" . $externalSeason->getName() . "\"", E_ERROR);
        }
        return $externalCompetition;
    }

    public function importTeams(
        ExternalSourceTeam $externalSourceTeam,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league,
        Season $season
    ): void {
        $externalCompetition = null;
        if ($externalSourceTeam instanceof ExternalSourceCompetition) {
            $externalCompetition = $this->getExternalCompetition(
                $externalSourceTeam,
                $externalSource,
                $sport,
                $association,
                $league,
                $season
            );
        }
        if ($externalCompetition === null) {
            $this->logger->error("no competition could be found for external competition ");
            return;
        }

        $this->teamImportService->import(
            $externalSource,
            $externalSourceTeam->getTeams($externalCompetition)
        );
    }

    public function importTeamCompetitors(
        ExternalSourceTeamCompetitor $externalSourceTeamCompetitor,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league,
        Season $season
    ): void {
        $externalCompetition = null;
        if ($externalSourceTeamCompetitor instanceof ExternalSourceCompetition) {
            $externalCompetition = $this->getExternalCompetition(
                $externalSourceTeamCompetitor,
                $externalSource,
                $sport,
                $association,
                $league,
                $season
            );
        }
        if ($externalCompetition === null) {
            $this->logger->error("no competition could be found for external competition ");
            return;
        }

        $competitors = $externalSourceTeamCompetitor->getTeamCompetitors($externalCompetition);
        $this->teamCompetitorImportService->import($externalSource, array_values($competitors));
    }

    public function importStructure(
        ExternalSourceStructure $externalSourceStructure,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league,
        Season $season
    ): void {
        $externalCompetition = null;
        if ($externalSourceStructure instanceof ExternalSourceCompetition) {
            $externalCompetition = $this->getExternalCompetition(
                $externalSourceStructure,
                $externalSource,
                $sport,
                $association,
                $league,
                $season
            );
        }
        if ($externalCompetition === null) {
            $this->logger->error("no competition could be found for external competition ");
            return;
        }
        $structure = $externalSourceStructure->getStructure($externalCompetition);
        if ($structure === null) {
            $this->logger->error("no structure could be found for external competition");
            return;
        }
        $this->structureImportService->import($externalSource, $structure);
    }

    public function importSchedule(
        ExternalSourceAgainstGame $externalSourceAgainstGame,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league,
        Season $season
    ): void {
        $externalCompetition = null;
        if ($externalSourceAgainstGame instanceof ExternalSourceCompetition) {
            $externalCompetition = $this->getExternalCompetition(
                $externalSourceAgainstGame,
                $externalSource,
                $sport,
                $association,
                $league,
                $season
            );
        }
        if ($externalCompetition === null) {
            $this->logger->error("no competition could be found for external competition ");
            return;
        }
        $structure = null;
        if ($externalSourceAgainstGame instanceof ExternalSourceStructure) {
            $structure = $externalSourceAgainstGame->getStructure($externalCompetition);
        }
        if ($structure === null) {
            $this->logger->error("no structure could be found for external competition ");
            return;
        }
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

        $batchNrs = $externalSourceAgainstGame->getBatchNrs($externalCompetition);
        $filteredBatchNrs = $this->getBatchNrsToImport($competition, $nrOfPlaces, $batchNrs);

        foreach ($filteredBatchNrs as $batchNr) {
            $externalGames = $externalSourceAgainstGame->getAgainstGames($externalCompetition, $batchNr);

            $this->againstGameImportService->importSchedule(
                $externalSource,
                array_values($externalGames)
            );
        }
    }

    public function importAgainstGameDetails(
        ExternalSourceAgainstGame $externalSourceImplementation,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association,
        League $league,
        Season $season,
        Period $period
    ): void {
        $externalCompetition = null;
        if ($externalSourceImplementation instanceof ExternalSourceCompetition) {
            $externalCompetition = $this->getExternalCompetition(
                $externalSourceImplementation,
                $externalSource,
                $sport,
                $association,
                $league,
                $season
            );
        }
        if ($externalCompetition === null) {
            $this->logger->error('external source "' . $externalSource->getName() .'" could not find a competition for externals');
            return;
        }
        $structure = null;
        if ($externalSourceImplementation instanceof ExternalSourceStructure) {
            $structure = $externalSourceImplementation->getStructure($externalCompetition);
        }
        if ($structure === null) {
            $this->logger->warning("no structure found for external competition " . $externalCompetition->getName());
            return;
        }
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
        $games = $this->againstGameRepos->getCompetitionGames($competition, null, null, $period);
        if ($this->eventSender !== null) {
            if ($this->eventSender instanceof ImportGameDetailsEvent) {
                $this->againstGameImportService->setEventSender($this->eventSender);
            }
        }
        $game = null;
        try {
            foreach ($games as $game) {
                $externalGame = $this->getExternalAgainstGame($externalSourceImplementation, $externalSource, $externalCompetition, $game);
                if ($externalGame->getState() !== State::Finished) {
                    $this->logger->info("game " . (string)$externalGame->getId() . " is not finished");
                    continue;
                }
                $this->personImportService->importByAgainstGame(
                    $externalSource,
                    $externalGame
                );

                $this->againstGameImportService->importDetails(
                    $externalSource,
                    $externalGame
                );
            }
        } catch (\Exception $e) {
            if ($game !== null) {
                // all batch should be stopped, because of editing playerperiods
                $competitors = array_values($competition->getTeamCompetitors()->toArray());
                $gameOutput = new AgainstGameOutput(new CompetitorMap($competitors), $this->logger);
                $gameOutput->output($game, $e->getMessage());
            }
        }
    }

    public function importPersonImages(
        ExternalSourceImplementation $externalSourceImplementation,
        League $league,
        Season $season,
        string $localOutputPath,
        string $publicOutputPath,
        int $maxWidth
    ): void {
        $externalSource = $externalSourceImplementation->getExternalSource();
        if (!($externalSourceImplementation instanceof ExternalSourcePerson)
        || !($externalSourceImplementation instanceof ExternalSourceTeam)) {
            return;
        }
        $competition = $league->getCompetition($season);
        if ($competition === null) {
            return;
        }
        $nrUpdated = 0;
        $maxUpdated = 10;

        $teams = $competition->getTeamCompetitors()->map(function (TeamCompetitorBase $teamCompetitor): TeamBase {
            return $teamCompetitor->getTeam();
        });
        foreach ($teams as $team) {
            $activePlayers = $team->getPlayers()->filter(function (Player $player) use ($season): bool {
                return $player->getEndDateTime() > $season->getStartDateTime();
            });
            foreach ($activePlayers as $activePlayer) {
                $person = $activePlayer->getPerson();
                $personExternalId = $this->personAttacherRepos->findExternalId(
                    $externalSource,
                    $person
                );
                if ($personExternalId === null) {
                    continue;
                }
                if (!$this->personImportService->importImage(
                    $externalSourceImplementation,
                    $externalSource,
                    $person,
                    $localOutputPath,
                    $publicOutputPath,
                    $maxWidth
                )) {
                    continue;
                }
                if (++$nrUpdated === $maxUpdated) {
                    return;
                }
            }
        }
    }

    public function importTeamImages(
        ExternalSourceImplementation $externalSourceImplementation,
        League $league,
        Season $season,
        string $localOutputPath,
        string $publicOutputPath,
        int $maxWidth
    ): void {
        $externalSource = $externalSourceImplementation->getExternalSource();
        if (!($externalSourceImplementation instanceof ExternalSourcePerson)
            || !($externalSourceImplementation instanceof ExternalSourceTeam)) {
            return;
        }
        $competition = $league->getCompetition($season);
        if ($competition === null) {
            return;
        }
        $nrUpdated = 0;
        $maxUpdated = 10;

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
            if (!$this->teamImportService->importImage(
                $externalSourceImplementation,
                $externalSource,
                $team,
                $localOutputPath,
                $publicOutputPath,
                $maxWidth
            )) {
                continue;
            }
            if (++$nrUpdated === $maxUpdated) {
                return;
            }
        }
    }

    /**
     * als batchNr is finished and more than 2 days old
     *
     * @param Competition $competition
     * @param int $nrOfPlaces
     * @param list<int> $batchNrs
     * @return list<int>
     */
    protected function getBatchNrsToImport(Competition $competition, int $nrOfPlaces, array $batchNrs): array
    {
        $batchNrsRet = [];

        foreach ($batchNrs as $batchNr) {
            $hasBatchNrGames = $this->againstGameRepos->hasCompetitionGames(
                $competition,
                null,
                $batchNr
            );
            if ($hasBatchNrGames) {
                continue;
            }
            $batchNrsRet[] = $batchNr;
            if (count($batchNrsRet) === 4) {
                return $batchNrsRet;
            }
        }

        foreach ($batchNrs as $batchNr) {
            $batchNrGamePlaces = $this->againstGameRepos->getNrOfCompetitionGamePlaces(
                $competition,
                State::Finished,
                $batchNr
            );
            if ($batchNrGamePlaces >= ($nrOfPlaces-1)) {
                continue;
            }
            $batchNrsRet[] = $batchNr;
            if (count($batchNrsRet) === 4) {
                return $batchNrsRet;
            }
        }
        return $batchNrsRet;
    }
}
