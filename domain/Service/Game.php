<?php

namespace SportsImport\Service;

use DateTimeImmutable;
use Exception;
use Sports\Competition;
use Sports\Competitor;
use SportsImport\ExternalSource;
use Sports\Game\Repository as GameRepository;
use Sports\Game\Score\Repository as GameScoreRepository;
use Sports\Structure\Repository as StructureRepository;
use SportsImport\Attacher\Game\Repository as GameAttacherRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use Sports\Game as GameBase;
use Sports\Game\Service as GameService;
use SportsImport\Attacher\Game as GameAttacher;
use Psr\Log\LoggerInterface;
use Sports\Poule;
use Sports\Place;

class Game
{
    /**
     * @var GameRepository
     */
    protected $gameRepos;
    /**
     * @var GameScoreRepository
     */
    protected $gameScoreRepos;
    /**
     * @var GameAttacherRepository
     */
    protected $gameAttacherRepos;
    /**
     * @var CompetitionAttacherRepository
     */
    protected $competitionAttacherRepos;
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var GameService
     */
    protected $gameService;
    /**
     * @var LoggerInterface
     */
    private $logger;

    // public const MAX_DAYS_BACK = 8;

    public function __construct(
        GameRepository $gameRepos,
        GameScoreRepository $gameScoreRepos,
        StructureRepository $structureRepos,
        GameAttacherRepository $gameAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->gameRepos = $gameRepos;
        $this->gameScoreRepos = $gameScoreRepos;
        $this->structureRepos = $structureRepos;
        $this->gameAttacherRepos = $gameAttacherRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
        $this->gameService = new GameService();
    }
//
//    protected function getDeadLine(): DateTimeImmutable {
//        return (new DateTimeImmutable())->modify("-" . static::MAX_DAYS_BACK . " days");
//    }


    /**
     * @param ExternalSource $externalSource
     * @param array|GameBase[] $externalSourceGames
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceGames)
    {
        foreach ($externalSourceGames as $externalSourceGame) {
            $externalId = $externalSourceGame->getId();
            $gameAttacher = $this->gameAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($gameAttacher === null) {
                $game = $this->createGame($externalSource, $externalSourceGame);
                if ($game === null) {
                    continue;
                }
                $gameAttacher = new GameAttacher(
                    $game,
                    $externalSource,
                    $externalId
                );
                $this->gameAttacherRepos->save($gameAttacher);
            } else {
                $this->editGame($gameAttacher->getImportable(), $externalSourceGame);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createGame(ExternalSource $externalSource, GameBase $externalSourceGame): ?GameBase
    {
        $poule = $this->getPouleFromExternal($externalSource, $externalSourceGame->getPoule());
        if ($poule === null) {
            return null;
        }
        $game = new GameBase($poule, $externalSourceGame->getBatchNr(), $externalSourceGame->getStartDateTime());
        $game->setState($externalSourceGame->getState());
        // referee
        // field

        return null;
        // @TODO DEPRECATED
//        foreach ($externalSourceGame->getPlaces() as $externalSourceGamePlace) {
//            $externSourcePlace = $this->getPlaceFromExternal($externalSource, $externalSourceGamePlace->getPlace()->getCompetitor());
//            $place = $this->getPlaceFromPoule($poule, $externSourcePlace);
//            if ($place === null) {
//                return null;
//            }
//            $game->addPlace($place, $externalSourceGamePlace->getHomeaway());
//        }
//
//        $this->gameService->addScores($game, $externalSourceGame->getScores()->toArray());
//
//        $this->gameRepos->save($game);
//        return $game;
    }

    protected function getPouleFromExternal(ExternalSource $externalSource, Poule $externalPoule): ?Poule
    {
        $externalCompetition = $externalPoule->getRound()->getNumber()->getCompetition();

        $competition = $this->competitionAttacherRepos->findImportable(
            $externalSource,
            $externalCompetition->getId()
        );
        if ($competition === null) {
            return null;
        }
        $structure = $this->structureRepos->getStructure($competition);
        if ($structure === null) {
            return null;
        }
        return $structure->getFirstRoundNumber()->getRounds()->first()->getPoules()->first();
    }

    protected function getPlaceFromPoule(Poule $poule, Competitor $competitor): ?Place
    {
        return null;
        // @TODO DEPRECATED
//        $places = $poule->getPlaces()->filter(function (Place $place) use ($competitor): bool {
//            return $place->getCompetitor() !== null && $place->getCompetitor()->getId() === $competitor->getId();
//        });
//        if ($places->count() !== 1) {
//            return null;
//        }
//        return $places->first();
    }

    protected function getPlaceFromExternal(ExternalSource $externalSource, Place $externalPlace): ?Place
    {
        return null;
        // @TODO DEPRECATED
        // return $this->placeAttacherRepos->findImportable($externalSource, $externalPlace->getId());
    }

    protected function editGame(GameBase $game, GameBase $externalSourceGame)
    {
        $game->setState($externalSourceGame->getState());
        $game->setStartDateTime($externalSourceGame->getStartDateTime());
        // referee
        // field
        $this->gameScoreRepos->removeScores($game);
        $this->gameService->addScores($game, $game->getScores()->toArray());

        $this->gameRepos->save($game);
    }
}
