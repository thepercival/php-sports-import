<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers\Game;

use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Sports\Competitor\Map as CompetitorMap;
use Sports\Game;
use Sports\Game\Against as AgainstGame;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Game\Event\Card;
use Sports\Game\Event\Goal;
use Sports\Game\Place\Against as AgainstGamePlace;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Person;
use Sports\Poule;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Score\Creator as ScoreCreator;
use Sports\Sport;
use Sports\State;
use Sports\Structure\Repository as StructureRepository;
use Sports\Team;
use Sports\Team\Player;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Game\Against as AgainstGameAttacher;
use SportsImport\Attacher\Game\Against\Repository as AgainstGameAttacherRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\ExternalSource;
use SportsImport\ImporterHelpers\Person as PersonImporterHelper;
use SportsImport\Queue\Game\ImportDetailsEvent as ImportGameDetailsEvent;
use SportsImport\Queue\Game\ImportEvent as ImportGameEvent;

class Against
{
    protected ImportGameEvent|ImportGameDetailsEvent|null $eventSender = null;

    // public const MAX_DAYS_BACK = 8;

    public function __construct(
        protected PersonImporterHelper $personHelper,
        protected AgainstGameRepository $againstGameRepos,
        protected AgainstScoreRepository $againstScoreRepos,
        protected StructureRepository $structureRepos,
        protected AgainstGameAttacherRepository $againstGameAttacherRepos,
        protected SportAttacherRepository $sportAttacherRepos,
        protected CompetitionAttacherRepository $competitionAttacherRepos,
        protected PersonAttacherRepository $personAttacherRepos,
        protected TeamAttacherRepository $teamAttacherRepos,
        protected LoggerInterface $logger
    ) {
    }
//
//    protected function getDeadLine(): DateTimeImmutable {
//        return (new DateTimeImmutable())->modify("-" . static::MAX_DAYS_BACK . " days");
//    }

    public function setEventSender(ImportGameEvent|ImportGameDetailsEvent $eventSender): void
    {
        $this->eventSender = $eventSender;
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<AgainstGame> $externalGames
     * @throws Exception
     */
    public function importSchedule(ExternalSource $externalSource, array $externalGames): void
    {
        foreach ($externalGames as $externalGame) {
            $poule = $this->getPouleFromExternal($externalSource, $externalGame->getPoule());
            if ($poule === null) {
                continue;
            }
            $teamCompetitors = $poule->getRound()->getNumber()->getCompetition()->getTeamCompetitors()->toArray();
            $competitorMap = new CompetitorMap(array_values($teamCompetitors));
            $gameOutput = new AgainstGameOutput($competitorMap, $this->logger);

            $externalId = $externalGame->getId();
            if ($externalId === null) {
                continue;
            }
            $gameAttacher = $this->againstGameAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
            );

            $gameCreated = false;
            if ($gameAttacher === null) {
                $game = $this->createGame($poule, $externalSource, $externalGame);
                if ($game === null) {
                    continue;
                }
                $gameAttacher = new AgainstGameAttacher(
                    $game,
                    $externalSource,
                    (string)$externalId
                );
                $this->againstGameAttacherRepos->save($gameAttacher);

                $gameOutput->output($game, "created => ");
                $gameCreated = true;
            }

            if ($externalGame->getState() === State::Finished) {
                $this->personHelper->importByAgainstGame(
                    $externalSource,
                    $externalGame
                );
                $this->importDetails($externalSource, $externalGame);
            } else {
                $gameRescheduled = false;
                $game = $gameAttacher->getImportable();
                $oldStartDateTime = $game->getStartDateTime();
                if ($game->getStartDateTime()->getTimestamp() !== $externalGame->getStartDateTime()->getTimestamp()) {
                    $gameRescheduled = true;
                    $game->setStartDateTime($externalGame->getStartDateTime());
                    $this->againstGameRepos->save($game);
                    if ($this->eventSender instanceof ImportGameEvent) {
                        $this->eventSender->sendUpdateGameEvent($game, $oldStartDateTime);
                    }
                }
                if ($gameCreated || $gameRescheduled) {
                    if ($this->eventSender !== null && $this->eventSender instanceof ImportGameEvent) {
                        $this->eventSender->sendUpdateGameEvent($game);
                    }
                }
            }
        }
    }

    protected function createGame(Poule $poule, ExternalSource $externalSource, AgainstGame $externalGame): AgainstGame|null
    {
        $sport = $this->getSportFromExternal($externalSource, $externalGame->getCompetitionSport()->getSport());
        if ($sport === null) {
            return null;
        }
        $competitionSport = $poule->getCompetition()->getSport($sport);
        if ($competitionSport === null) {
            return null;
        }

        $game = new AgainstGame(
            $poule,
            $externalGame->getBatchNr(),
            $externalGame->getStartDateTime(),
            $competitionSport,
            $externalGame->getGameRoundNumber()
        );
        $game->setStartDateTime($externalGame->getStartDateTime());
        $game->setState($externalGame->getState());

        foreach ($externalGame->getPlaces() as $externalSourceGamePlace) {
            $place = $poule->getPlace($externalSourceGamePlace->getPlace()->getPlaceNr());
            new AgainstGamePlace($game, $place, $externalSourceGamePlace->getSide());
        }

        $this->againstGameRepos->save($game);
        return $game;
    }

    public function importDetails(ExternalSource $externalSource, AgainstGame $externalGame): void
    {
        $externalId = $externalGame->getId();
        $gameAttacher = $this->againstGameAttacherRepos->findOneByExternalId(
            $externalSource,
            (string)$externalId
        );
        if ($gameAttacher === null) {
            $externalCompetition = $externalGame->getPoule()->getRound()->getNumber()->getCompetition();
            $teamCompetitors = array_values($externalCompetition->getTeamCompetitors()->toArray());
            $placeLocationMap = new CompetitorMap($teamCompetitors);
            $gameOutput = new AgainstGameOutput($placeLocationMap, $this->logger);
            $gameOutput->output($externalGame, "no game found for external  ");
            $this->logger->warning("no game found for external gameid " . (string)$externalId . " and external source \"" . $externalSource->getName() ."\"") ;
            return;
        }


        $game = $gameAttacher->getImportable();
//        if ($game === null) {
//            $teamCompetitors = array_values($externalCompetition->getTeamCompetitors()->toArray());
//            $placeLocationMap = new CompetitorMap($teamCompetitors);
//            $gameOutput = new AgainstGameOutput($placeLocationMap, $this->logger);
//            $gameOutput->output($externalGame, "no game found for external  ");
//            $this->logger->warning("no game found for external gameid " . (string)$externalId . " and external source \"" . $externalSource->getName() ."\"") ;
//        }

        $game->setState($externalGame->getState());

        $this->removeDetails($game);

        (new ScoreCreator())->addAgainstScores($game, array_values($externalGame->getScores()->toArray()));

        foreach ($externalGame->getPlaces() as $externalGamePlace) {
            foreach ($externalGamePlace->getParticipations() as $externalParticipation) {
                $player = $this->getPlayerFromExternal($game, $externalSource, $externalParticipation->getPlayer());
                if ($player === null) {
                    continue;
                }
                $gameParticipation = new Game\Participation(
                    $this->getGamePlaceFromExternal($game, $externalGamePlace->getPlace()->getPlaceNr()),
                    $player,
                    $externalParticipation->getBeginMinute(),
                    $externalParticipation->getEndMinute()
                );

                foreach ($externalParticipation->getCards() as $card) {
                    new Card($card->getMinute(), $gameParticipation, $card->getType());
                }
                foreach ($externalParticipation->getGoals() as $externalGoal) {
                    $goal = new Goal($externalGoal->getMinute(), $gameParticipation);
                    $goal->setPenalty($externalGoal->getPenalty());
                    $goal->setOwn($externalGoal->getOwn());
                    if ($externalGoal->getAssistGameParticipation() === null) {
                        continue;
                    }
                    $externalAssistParticipation = $externalGoal->getAssistGameParticipation();
                    if ($externalAssistParticipation === null) {
                        continue;
                    }
                    $externalAssistPlayer = $externalAssistParticipation->getPlayer();
                    $assistPlayer = $this->getPlayerFromExternal($game, $externalSource, $externalAssistPlayer);
                    if ($assistPlayer === null) {
                        continue;
                    }
                    $assistGameParticipation = $game->getParticipation($assistPlayer->getPerson());
                    if ($assistGameParticipation === null) {
                        continue;
                    }
                    $goal->setAssistGameParticipation($assistGameParticipation);
                }
            }
        }
        $this->againstGameRepos->save($game);
        if ($this->eventSender !== null && $this->eventSender instanceof ImportGameDetailsEvent) {
            $this->eventSender->sendUpdateGameDetailsEvent($game);
        }
    }

    protected function getGamePlaceFromExternal(AgainstGame $game, int $placeNr): AgainstGamePlace
    {
        foreach ($game->getPlaces() as $gamePlace) {
            if ($gamePlace->getPlace()->getPlaceNr() === $placeNr) {
                return $gamePlace;
            }
        }
        throw new \Exception('gameplace not found for externalplacenr: ' . $placeNr, E_ERROR);
    }

    protected function getSportFromExternal(ExternalSource $externalSource, Sport $externalSport): Sport|null
    {
        $sport = $this->sportAttacherRepos->findImportable(
            $externalSource,
            (string)$externalSport->getId()
        );
        if ($sport === null) {
            $this->logger->warning("no sport found for external sport " . $externalSport->getName());
            return null;
        }

        return $sport;
    }

    protected function getPouleFromExternal(ExternalSource $externalSource, Poule $externalPoule): Poule|null
    {
        $externalCompetition = $externalPoule->getRound()->getNumber()->getCompetition();

        $competition = $this->competitionAttacherRepos->findImportable(
            $externalSource,
            (string)$externalCompetition->getId()
        );
        if ($competition === null) {
            $this->logger->warning("no competition found for external competition " . $externalCompetition->getName());
            return null;
        }
        $structure = $this->structureRepos->getStructure($competition);
        $rootRound = $structure->getFirstRoundNumber()->getRounds()->first();
        if ($rootRound === false) {
            return null;
        }
        $firstPoule = $rootRound->getPoules()->first();
        if ($firstPoule === false) {
            return null;
        }
        return $firstPoule;
    }

    protected function getPlayerFromExternal(AgainstGame $game, ExternalSource $externalSource, Player $externalPlayer): Player|null
    {
        $externalTeam = $externalPlayer->getTeam();
        $team = $this->getTeamFromExternal($externalSource, $externalTeam);
        if ($team === null) {
            return null;
        }

        $externalPerson = $externalPlayer->getPerson();
        $person = $this->getPersonFromExternal($externalSource, $externalPerson);
        if ($person === null) {
            return null;
        }

        $player = $person->getPlayer($team, $game->getStartDateTime());
        if ($player === null) {
            $this->logger->warning("no player found for external person " . $externalPerson->getName() . " and datetime " . $game->getStartDateTime()->format(DateTimeImmutable::ATOM));
            return null;
        }
        return $player;
    }

    protected function getPersonFromExternal(ExternalSource $externalSource, Person $externalPerson): Person|null
    {
        $person = $this->personAttacherRepos->findImportable(
            $externalSource,
            (string)$externalPerson->getId()
        );
        if ($person === null) {
            $this->logger->warning("no person found for external person " . $externalPerson->getName());
            return null;
        }
        return $person;
    }

    protected function getTeamFromExternal(ExternalSource $externalSource, Team $externalTeam): Team|null
    {
        $team = $this->teamAttacherRepos->findImportable(
            $externalSource,
            (string)$externalTeam->getId()
        );
        if ($team === null) {
            $this->logger->warning("no team found for external team " . $externalTeam->getName());
            return null;
        }
        return $team;
    }


    protected function removeDetails(AgainstGame $game): void
    {
        foreach ($game->getPlaces() as $gamePlace) {
            while ($participation = $gamePlace->getParticipations()->first()) {
                $gamePlace->getParticipations()->removeElement($participation);
            }
        }

        $this->againstScoreRepos->removeScores($game);

        $this->againstGameRepos->save($game);
    }
}
