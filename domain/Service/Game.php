<?php

namespace SportsImport\Service;

use DateTimeImmutable;
use Doctrine\Common\Collections\Collection;
use Exception;
use Sports\Person;
use Sports\Competitor;
use Sports\Team\Player;
use SportsImport\ExternalSource;
use Sports\Game\Repository as GameRepository;
use Sports\Game\Score\Repository as GameScoreRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsImport\Attacher\Game\Repository as GameAttacherRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use Sports\Game as GameBase;
use Sports\Game\Score\Creator as GameScoreCreator;
use SportsImport\Attacher\Game as GameAttacher;
use Psr\Log\LoggerInterface;
use Sports\Poule;
use Sports\Place;
use Sports\Team;
use Sports\Game\Event\Goal;
use Sports\Game\Event\Card;
use Sports\Output\Game as GameOutput;
use SportsImport\Queue\Game\ImportDetailsEvent as ImportGameDetailsEvent;
use SportsImport\Queue\Game\ImportEvent as ImportGameEvent;

class Game
{
    protected GameRepository $gameRepos;
    protected GameScoreRepository $gameScoreRepos;
    protected GameAttacherRepository $gameAttacherRepos;
    protected CompetitionAttacherRepository $competitionAttacherRepos;
    protected PersonAttacherRepository $personAttacherRepos;
    protected TeamAttacherRepository $teamAttacherRepos;
    protected StructureRepository $structureRepos;
    protected GameScoreCreator $gameScoreCreator;
    private LoggerInterface $logger;

    /**
     * @var ImportGameEvent | ImportGameDetailsEvent | null
     */
    protected $eventSender;

    // public const MAX_DAYS_BACK = 8;

    public function __construct(
        GameRepository $gameRepos,
        GameScoreRepository $gameScoreRepos,
        StructureRepository $structureRepos,
        GameAttacherRepository $gameAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        PersonAttacherRepository $personAttacherRepos,
        TeamAttacherRepository $teamAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->gameRepos = $gameRepos;
        $this->gameScoreRepos = $gameScoreRepos;
        $this->structureRepos = $structureRepos;
        $this->gameAttacherRepos = $gameAttacherRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
        $this->personAttacherRepos = $personAttacherRepos;
        $this->teamAttacherRepos = $teamAttacherRepos;
        $this->gameScoreCreator = new GameScoreCreator();
    }
//
//    protected function getDeadLine(): DateTimeImmutable {
//        return (new DateTimeImmutable())->modify("-" . static::MAX_DAYS_BACK . " days");
//    }

    /**
     * @param ImportGameEvent | ImportGameDetailsEvent $eventSender
     */
    public function setEventSender( $eventSender ) {
        $this->eventSender = $eventSender;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|GameBase[] $externalGames
     * @throws Exception
     */
    public function importSchedule(ExternalSource $externalSource, array $externalGames)
    {
        foreach ($externalGames as $externalGame) {
            $poule = $this->getPouleFromExternal($externalSource, $externalGame->getPoule());
            if ($poule === null) {
                continue;
            }
            /** @var Competitor[]|Collection $teamCompetitors */
            $teamCompetitors = $poule->getRound()->getNumber()->getCompetition()->getTeamCompetitors();
            $placeLocationMap = new Place\Location\Map( $teamCompetitors->toArray() );
            $gameOutput = new GameOutput( $placeLocationMap, $this->logger);

            $externalId = $externalGame->getId();
            $gameAttacher = $this->gameAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );

            if ($gameAttacher === null) {
                $game = $this->createGame($poule, $externalSource, $externalGame);
                if ($game === null) {
                    continue;
                }
                $gameAttacher = new GameAttacher(
                    $game,
                    $externalSource,
                    $externalId
                );
                $this->gameAttacherRepos->save($gameAttacher);

                $gameOutput->output( $game, "created => ");
                continue;
            }
            $game = $gameAttacher->getImportable();
            if ($game === null) {
                continue;
            }
            if( $game->getStartDateTime() != $externalGame->getStartDateTime() ) {
                $gameOutput->output( $game, "reschedule => ");
            }
            $game->setStartDateTime($externalGame->getStartDateTime());
            $this->gameRepos->save($game);
        }
    }

    protected function createGame(Poule $poule, ExternalSource $externalSource, GameBase $externalGame): ?GameBase
    {
        $game = new GameBase($poule, $externalGame->getBatchNr(), $externalGame->getStartDateTime());
        $game->setStartDateTime($externalGame->getStartDateTime());
        $game->setState($externalGame->getState());

        foreach ($externalGame->getPlaces() as $externalSourceGamePlace) {
            $place = $poule->getPlace($externalSourceGamePlace->getPlace()->getPlaceNr());
            if ($place === null) {
                return null;
            }
            $game->addPlace($place, $externalSourceGamePlace->getHomeaway());
        }

        $this->gameRepos->save($game);
        return $game;
    }

    /**
     * @param ExternalSource $externalSource
     * @param GameBase $externalGame
     */
    public function importDetails(ExternalSource $externalSource, GameBase $externalGame )
    {
        $externalId = $externalGame->getId();
        $gameAttacher = $this->gameAttacherRepos->findOneByExternalId(
            $externalSource,
            $externalId
        );
        $externalCompetition = $externalGame->getPoule()->getRound()->getNumber()->getCompetition();
        $game = $gameAttacher->getImportable();
        if ($game === null) {
            $placeLocationMap = new Place\Location\Map( $externalCompetition->getTeamCompetitors()->toArray() );
            $gameOutput = new GameOutput( $placeLocationMap, $this->logger);
            $gameOutput->output( $externalGame, "no game found for external  ");
            $this->logger->warning( "no game found for external gameid " . $externalId . " and external source \"" . $externalSource->getName() ."\"") ;
        }

        $game->setState($externalGame->getState());

        $this->removeDetails( $game );

        $this->gameScoreCreator->addScores($game, $externalGame->getScores()->toArray());

       foreach( $externalGame->getParticipations() as $externalParticipation ) {
           $player = $this->getPlayerFromExternal($game, $externalSource, $externalParticipation->getPlayer() );
           if ($player === null) {
               continue;
           }
           $gameParticipation = new GameBase\Participation(
               $game, $player,
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
               $assistPlayer = $this->getPlayerFromExternal($game, $externalSource, $externalGoal->getAssistGameParticipation()->getPlayer() );
               if ($assistPlayer === null) {
                   continue;
               }
               $assistGameParticipation = $game->getParticipation($assistPlayer->getPerson() );
               if ($assistGameParticipation === null) {
                   continue;
               }
               $goal->setAssistGameParticipation($assistGameParticipation);
           }
       }
       $this->gameRepos->save($game);
       if( $this->eventSender !== null && $this->eventSender instanceof ImportGameDetailsEvent ) {
           $this->eventSender->sendUpdateGameDetailsEvent( $game );
       }
    }

    protected function getPouleFromExternal(ExternalSource $externalSource, Poule $externalPoule): ?Poule
    {
        $externalCompetition = $externalPoule->getRound()->getNumber()->getCompetition();

        $competition = $this->competitionAttacherRepos->findImportable(
            $externalSource,
            $externalCompetition->getId()
        );
        if ($competition === null) {
            $this->logger->warning("no competition found for external competition " . $externalCompetition->getName() );
            return null;
        }
        $structure = $this->structureRepos->getStructure($competition);
        if ($structure === null) {
            $this->logger->warning("no structure found for external competition " . $externalCompetition->getName() );
            return null;
        }
        return $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();
    }

    protected function getPlayerFromExternal(GameBase $game, ExternalSource $externalSource, Player $externalPlayer): ?Player
    {
        $externalTeam = $externalPlayer->getTeam();
        $team = $this->getTeamFromExternal( $externalSource, $externalTeam );
        if( $team === null ) {
            return null;
        }

        $externalPerson = $externalPlayer->getPerson();
        $person = $this->getPersonFromExternal( $externalSource, $externalPerson );
        if( $person === null ) {
            return null;
        }

        $player = $person->getPlayer( $team, $game->getStartDateTime() );
        if ($player === null) {
            $this->logger->warning("no player found for external person " . $externalPerson->getName() . " and datetime " . $game->getStartDateTime()->format( DateTimeImmutable::ATOM)  );
            return null;
        }
        return $player;
    }

    protected function getPersonFromExternal(ExternalSource $externalSource, Person $externalPerson): ?Person
    {
        $person = $this->personAttacherRepos->findImportable(
            $externalSource,
            $externalPerson->getId()
        );
        if ($person === null) {
            $this->logger->warning("no person found for external person " . $externalPerson->getName() );
            return null;
        }
        return $person;
    }

    protected function getTeamFromExternal(ExternalSource $externalSource, Team $externalTeam): ?Team
    {
        $team = $this->teamAttacherRepos->findImportable(
            $externalSource,
            $externalTeam->getId()
        );
        if ($team === null) {
            $this->logger->warning("no team found for external team " . $externalTeam->getName() );
            return null;
        }
        return $team;
    }


    protected function removeDetails( GameBase $game )
    {
        while( $game->getParticipations()->count() > 0 ) {
            $gameParticipation = $game->getParticipations()->first();
            $game->getParticipations()->removeElement($gameParticipation);
        }

        $this->gameScoreRepos->removeScores($game);

        $this->gameRepos->save($game);
    }
}
