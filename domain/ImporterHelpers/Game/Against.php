<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers\Game;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Sports\Competitor\StartLocationMap;
use Sports\Game;
use Sports\Game\Against as AgainstGame;
use Sports\Repositories\AgainstGameRepository;
use Sports\Game\Event\Card;
use Sports\Game\Event\Goal;
use Sports\Game\Participation as GameParticipation;
use Sports\Game\Place\Against as AgainstGamePlace;
use Sports\Game\State as GameState;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Output\Game\Column as GameColumn;
use Sports\Person;
use Sports\Poule;
use Sports\Repositories\AgainstScoreRepository;
use Sports\Score\Creator as ScoreCreator;
use Sports\Sport;
use Sports\Structure\Repository as StructureRepository;
use Sports\Team;
use Sports\Team\Player;
use SportsImport\Attachers\AgainstGameAttacher as AgainstGameAttacher;
use SportsImport\Attachers\CompetitionAttacher;
use SportsImport\Attachers\PersonAttacher;
use SportsImport\Attachers\SportAttacher;
use SportsImport\Attachers\TeamAttacher;
use SportsImport\ExternalSource;
use SportsImport\ImporterHelpers\Person as PersonImporterHelper;
use SportsImport\Queue\Game\ImportEvents as ImportGameEvents;
use SportsImport\Repositories\AttacherRepository;

final class Against
{
    protected ImportGameEvents|null $importGameEventsSender = null;

    /** @var AttacherRepository<AgainstGameAttacher>  */
    protected AttacherRepository $againstGameAttacherRepos;
    /** @var AttacherRepository<SportAttacher>  */
    protected AttacherRepository $sportAttacherRepos;
    /** @var AttacherRepository<CompetitionAttacher>  */
    protected AttacherRepository $competitionAttacherRepos;
    /** @var AttacherRepository<PersonAttacher>  */
    protected AttacherRepository $personAttacherRepos;
    /** @var AttacherRepository<TeamAttacher>  */
    protected AttacherRepository $teamAttacherRepos;

    // public const MAX_DAYS_BACK = 8;

    public function __construct(
        protected PersonImporterHelper $personHelper,
        protected AgainstGameRepository $againstGameRepos,
        protected AgainstScoreRepository $againstScoreRepos,
        protected StructureRepository $structureRepos,
        protected LoggerInterface $logger,
        protected EntityManagerInterface $entityManager,
    ) {
        $metaData = $entityManager->getClassMetadata(AgainstGameAttacher::class);
        $this->againstGameAttacherRepos = new AttacherRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(SportAttacher::class);
        $this->sportAttacherRepos = new AttacherRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(CompetitionAttacher::class);
        $this->competitionAttacherRepos = new AttacherRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(PersonAttacher::class);
        $this->personAttacherRepos = new AttacherRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(TeamAttacher::class);
        $this->teamAttacherRepos = new AttacherRepository($entityManager, $metaData);
    }
//
//    protected function getDeadLine(): DateTimeImmutable {
//        return (new DateTimeImmutable())->modify("-" . static::MAX_DAYS_BACK . " days");
//    }

    public function setEventSender(ImportGameEvents $importGameEventsSender): void
    {
        $this->importGameEventsSender = $importGameEventsSender;
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<AgainstGame> $externalGames
     * @param bool $onlyBasics
     * @throws Exception
     */
    public function importGames(ExternalSource $externalSource, array $externalGames, bool $onlyBasics): void
    {
        foreach ($externalGames as $externalGame) {
            $poule = $this->getPouleFromExternal($externalSource, $externalGame->getPoule());
            if ($poule === null) {
                continue;
            }

            $externalId = $externalGame->getId();
            if ($externalId === null) {
                continue;
            }
            $gameAttacher = $this->againstGameAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
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
                $this->entityManager->persist($gameAttacher);
                $this->entityManager->flush();

                $this->outputGame($game, "created => ");
            } else {
                $game = $gameAttacher->getImportable();
                $this->importBasics($externalSource, $externalGame);
            }

            if ($externalGame->getState() === GameState::Finished && !$onlyBasics) {
                $this->personHelper->importByAgainstGame(
                    $externalSource,
                    $game->getCompetitionSport()->getCompetition()->getSeason(),
                    $externalGame
                );
                $this->importScoresLineupsAndEvents($externalSource, $externalGame);
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

        $this->entityManager->persist($game);
        $this->entityManager->flush();
        $this->importGameEventsSender?->sendCreateEvent($game);
        return $game;
    }

    public function importBasics(ExternalSource $externalSource, AgainstGame $externalGame): void
    {
        $game = $this->getGameFromExternal($externalSource, $externalGame);
        if ($game === null) {
            return;
        }
        $oldStartDateTime = $game->getStartDateTime();
        $stateChanged = $game->getState() !== $externalGame->getState();
        $game->setState($externalGame->getState());

        $rescheduled = false;
        if ($game->getStartDateTime()->getTimestamp() !== $externalGame->getStartDateTime()->getTimestamp()) {
            $game->setStartDateTime($externalGame->getStartDateTime());
            $rescheduled = true;
        }

        $this->entityManager->persist($game);
        $this->entityManager->flush();
        if ($stateChanged || $rescheduled) {
            $this->importGameEventsSender?->sendUpdateBasicsEvent($game);
        }
        $rescheduledDescr = '';
        if ($rescheduled) {
            $this->importGameEventsSender?->sendRescheduleEvent($oldStartDateTime, $game);
            $rescheduledDescr = '(rescheduled)';
        }

        $this->outputGame($game, 'updated basics' . $rescheduledDescr . ' => ');
    }

    public function importScoresLineupsAndEvents(ExternalSource $externalSource, AgainstGame $externalGame): void
    {
        $game = $this->getGameFromExternal($externalSource, $externalGame);
        if ($game === null) {
            return;
        }

        $this->removeScoresLineupsAndEvents($game);
        (new ScoreCreator())->addAgainstScores($game, array_values($externalGame->getScores()->toArray()));

        // create gameParticipations
        foreach ($externalGame->getPlaces() as $externalGamePlace) {
            foreach ($externalGamePlace->getParticipations() as $externalParticipation) {
                $player = $this->getPlayerFromExternal($game, $externalSource, $externalParticipation->getPlayer());
                if ($player === null) {
                    continue;
                }
                new Game\Participation(
                    $this->getGamePlaceFromExternal($game, $externalGamePlace->getPlace()->getPlaceNr()),
                    $player,
                    $externalParticipation->getBeginMinute(),
                    $externalParticipation->getEndMinute()
                );
            }
        }

        foreach ($externalGame->getPlaces() as $externalGamePlace) {
            foreach ($externalGamePlace->getParticipations() as $externalParticipation) {
                $gameParticipation = $this->getGameParticipation($game, $externalSource, $externalParticipation);
                if ($gameParticipation === null) {
                    $this->logger->info('no gameparticipation found');
                    continue;
                }

                foreach ($externalParticipation->getCards() as $card) {
                    new Card($card->getMinute(), $gameParticipation, $card->getType());
                }
                foreach ($externalParticipation->getGoals() as $externalGoal) {
                    $goal = new Goal($externalGoal->getMinute(), $gameParticipation);
                    $goal->setPenalty($externalGoal->getPenalty());
                    $goal->setOwn($externalGoal->getOwn());
                    $externalAssistGameParticipation = $externalGoal->getAssistGameParticipation();
                    if ($externalAssistGameParticipation === null) {
                        continue;
                    }
                    $assistGameParticipation = $this->getGameParticipation(
                        $game,
                        $externalSource,
                        $externalAssistGameParticipation
                    );
                    if ($assistGameParticipation === null) {
                        $this->logger->info('no assist-gameparticipation found');
                        continue;
                    }
                    $goal->setAssistGameParticipation($assistGameParticipation);
                }
            }
        }
        $this->entityManager->persist($game);
        $this->entityManager->flush();
        $this->importGameEventsSender?->sendUpdateScoresLineupsAndEventsEvent($game);
        $this->outputGame($game, "updated scores,lineups,events => ", false);
    }

    protected function getGameParticipation(
        AgainstGame $game,
        ExternalSource $externalSource,
        GameParticipation $externalParticipation
    ): GameParticipation|null {
        $externalPlayer = $externalParticipation->getPlayer();
        $player = $this->getPlayerFromExternal($game, $externalSource, $externalPlayer);
        if ($player === null) {
            return null;
        }
        return $game->getParticipation($player->getPerson());
    }

    protected function getGameFromExternal(ExternalSource $externalSource, AgainstGame $externalGame): AgainstGame|null
    {
        $externalId = $externalGame->getId();
        $gameAttacher = $this->againstGameAttacherRepos->findOneByExternalId(
            $externalSource,
            (string)$externalId
        );
        if ($gameAttacher === null) {
            $externalCompetition = $externalGame->getPoule()->getRound()->getNumber()->getCompetition();
            $teamCompetitors = array_values($externalCompetition->getTeamCompetitors()->toArray());
            $gameOutput = new AgainstGameOutput(new StartLocationMap($teamCompetitors), $this->logger);
            $gameOutput->output($externalGame, "no game found for external  ");
            $this->logger->warning(
                "no game found for external gameid " . (string)$externalId . " and external source \"" . $externalSource->getName(
                ) . "\""
            );
            return null;
        }

        return $gameAttacher->getImportable();
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
        $attacher = $this->sportAttacherRepos->findOneByExternalId($externalSource, (string)$externalSport->getId());
        $sport = $attacher?->getImportable();
        if ($sport === null) {
            $this->logger->warning("no sport found for external sport " . $externalSport->getName());
            return null;
        }
        return $sport;
    }

    protected function getPouleFromExternal(ExternalSource $externalSource, Poule $externalPoule): Poule|null
    {
        $externalCompetition = $externalPoule->getRound()->getNumber()->getCompetition();

        $attacher = $this->competitionAttacherRepos->findOneByExternalId($externalSource, (string)$externalCompetition->getId());
        $competition = $attacher?->getImportable();
        if ($competition === null) {
            $this->logger->warning("no competition found for external competition " . $externalCompetition->getName());
            return null;
        }
        $structure = $this->structureRepos->getStructure($competition);
        // $rootRound = $structure->getFirstRoundNumber()->getRounds()->first();
        try {
            $rootRound = $structure->getSingleCategory()->getRootRound();
        } catch (Exception $e) {
            return null;
        }
//        if ($rootRound === false) {
//            return null;
//        }
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
        $attacher = $this->personAttacherRepos->findOneByExternalId($externalSource, (string)$externalPerson->getId());
        $person = $attacher?->getImportable();
        if ($person === null) {
            $this->logger->warning("no person found for external person " . $externalPerson->getName());
            return null;
        }
        return $person;
    }

    protected function getTeamFromExternal(ExternalSource $externalSource, Team $externalTeam): Team|null
    {
        $attacher = $this->teamAttacherRepos->findOneByExternalId($externalSource, (string)$externalTeam->getId());
        $team = $attacher?->getImportable();

        if ($team === null) {
            $this->logger->warning("no team found for external team " . $externalTeam->getName());
            return null;
        }
        return $team;
    }


    protected function removeScoresLineupsAndEvents(AgainstGame $game): void
    {
        foreach ($game->getPlaces() as $gamePlace) {
            while ($participation = $gamePlace->getParticipations()->first()) {
                $gamePlace->getParticipations()->removeElement($participation);
            }
        }

        $this->againstScoreRepos->removeScores($game);

        $this->entityManager->persist($game);
        $this->entityManager->flush();
    }

    protected function outputGame(AgainstGame $game, string $prefix, bool $onlyBasics = true): void
    {
        $teamCompetitorsTmp = $game->getRound()->getNumber()->getCompetition()->getTeamCompetitors();
        $teamCompetitors = array_values($teamCompetitorsTmp->toArray());
        $startLocationMap = new StartLocationMap($teamCompetitors);
        $gameOutput = new AgainstGameOutput($startLocationMap, $this->logger);
        $columns = $this->getGameColumns();
        if (!$onlyBasics) {
            $columns[] = GameColumn::ScoresLineupsAndEvents;
        }
        $gameOutput->output($game, $prefix, $columns);
    }

    /**
     * @return list<GameColumn>
     */
    protected function getGameColumns(): array
    {
        return [
            GameColumn::State,
            GameColumn::StartDateTime,
            GameColumn::GameRoundNumber,
            GameColumn::ScoreAndPlaces
        ];
    }
}
