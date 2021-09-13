<?php

namespace SportsImport\Service\Game;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Exception;
use Sports\Sport;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Competitor\Map as CompetitorMap;
use Sports\Person;
use Sports\Competitor;
use Sports\Team\Player;
use SportsImport\ExternalSource;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Score\Against\Repository as AgainstScoreRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsImport\Attacher\Game\Against\Repository as AgainstGameAttacherRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use Sports\Game\Against as AgainstGame;
use Sports\Game;
use Sports\Score\Creator as ScoreCreator;
use SportsImport\Attacher\Game\Against as AgainstGameAttacher;
use Psr\Log\LoggerInterface;
use Sports\Poule;
use Sports\Place;
use Sports\Game\Place\Against as AgainstGamePlace;
use Sports\Team;
use Sports\Game\Event\Goal;
use Sports\Game\Event\Card;
use Sports\Output\Game\Against as AgainstGameOutput;
use SportsImport\Queue\Game\ImportDetailsEvent as ImportGameDetailsEvent;
use SportsImport\Queue\Game\ImportEvent as ImportGameEvent;

class Against
{
    /**
     * @var ImportGameEvent | ImportGameDetailsEvent | null
     */
    protected $eventSender;

    // public const MAX_DAYS_BACK = 8;

    public function __construct(
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
     * @param array|AgainstGame[] $externalGames
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
                $externalId
            );

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
                continue;
            }
            $game = $gameAttacher->getImportable();
            if ($game === null) {
                continue;
            }
            $rescheduled = false;
            $oldStartDateTime = $game->getStartDateTime();
            if ($game->getStartDateTime() != $externalGame->getStartDateTime()) {
                $rescheduled = true;
            }
            $game->setStartDateTime($externalGame->getStartDateTime());
            $this->againstGameRepos->save($game);
            if ($rescheduled) {
                if ($this->eventSender !== null && $this->eventSender instanceof ImportGameEvent) {
                    $this->eventSender->sendUpdateGameEvent($game, $oldStartDateTime);
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
            $game->getPlaces()->add(
                new AgainstGamePlace($game, $place, $externalSourceGamePlace->getSide())
            );
        }

        $this->againstGameRepos->save($game);
        if ($this->eventSender !== null && $this->eventSender instanceof ImportGameEvent) {
            $this->eventSender->sendUpdateGameEvent($game);
        }
        return $game;
    }

    /**
     * @param ExternalSource $externalSource
     * @param AgainstGame $externalGame
     */
    public function importDetails(ExternalSource $externalSource, AgainstGame $externalGame): void
    {
        $externalId = $externalGame->getId();
        $gameAttacher = $this->againstGameAttacherRepos->findOneByExternalId(
            $externalSource,
            $externalId
        );
        $externalCompetition = $externalGame->getPoule()->getRound()->getNumber()->getCompetition();
        $game = $gameAttacher->getImportable();
        if ($game === null) {
            $teamCompetitors = array_values($externalCompetition->getTeamCompetitors()->toArray());
            $placeLocationMap = new CompetitorMap($teamCompetitors);
            $gameOutput = new AgainstGameOutput($placeLocationMap, $this->logger);
            $gameOutput->output($externalGame, "no game found for external  ");
            $this->logger->warning("no game found for external gameid " . (string)$externalId . " and external source \"" . $externalSource->getName() ."\"") ;
        }

        $game->setState($externalGame->getState());

        $this->removeDetails($game);

        (new ScoreCreator())->addAgainstScores($game, array_values($externalGame->getScores()->toArray()));

        foreach ($externalGame->getParticipations() as $externalParticipation) {
            $player = $this->getPlayerFromExternal($game, $externalSource, $externalParticipation->getPlayer());
            if ($player === null) {
                continue;
            }
            $gameParticipation = new Game\Participation(
                $game,
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
                $externalAssistPlayer = $externalAssistParticipation?->getPlayer();
                if ($externalAssistPlayer === null) {
                    continue;
                }
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
        $this->againstGameRepos->save($game);
        if ($this->eventSender !== null && $this->eventSender instanceof ImportGameDetailsEvent) {
            $this->eventSender->sendUpdateGameDetailsEvent($game);
        }
    }

    protected function getSportFromExternal(ExternalSource $externalSource, Sport $externalSport): Sport|null
    {
        $sport = $this->sportAttacherRepos->findImportable(
            $externalSource,
            $externalSport->getId()
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
            $externalCompetition->getId()
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
            $externalPerson->getId()
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
            $externalTeam->getId()
        );
        if ($team === null) {
            $this->logger->warning("no team found for external team " . $externalTeam->getName());
            return null;
        }
        return $team;
    }


    protected function removeDetails(AgainstGame $game): void
    {
        while ($participation = $game->getParticipations()->first()) {
            $game->getParticipations()->removeElement($participation);
        }

        $this->againstScoreRepos->removeScores($game);

        $this->againstGameRepos->save($game);
    }
}
