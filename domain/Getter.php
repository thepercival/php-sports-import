<?php

declare(strict_types=1);

namespace SportsImport;

use Psr\Log\LoggerInterface;

use Sports\Association;
use Sports\Competition;
use Sports\Competitor\Map as CompetitorMap;
use Sports\League;
use Sports\Output\Game\Against as AgainstGameOutput;
use Sports\Season;
use Sports\Game\Against as AgainstGame;
use Sports\Competition\Repository as CompetitionRepository;
use Sports\Game\Against\Repository as AgainstGameRepository;
use Sports\Sport;
use SportsImport\ExternalSource\CompetitionDetails;
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

class Getter
{
    protected ImportGameEvent|ImportGameDetailsEvent|null $eventSender = null;

    public function __construct(
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

    public function getSport(
        ExternalSource\Competitions $externalSourceCompetitions,
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
        $externalSport = $externalSourceCompetitions->getSport($sportAttacher->getExternalId());
        if ($externalSport === null) {
            throw new \Exception('external source "' . $externalSource->getName() .'" could not find a sport for externalId "' . $sportAttacher->getExternalId() . '"', E_ERROR);
        }
        return $externalSport;
    }

    public function getAgainstGame(
        CompetitionDetails $externalSourceCompetitionDetails,
        ExternalSource $externalSource,
        Competition $externalCompetition,
        string|int $gameId,
        bool $removeFromGameCache
    ): AgainstGame {
//        $gameAttacher = $this->againstGameAttacherRepos->findOneByExternalId(
//            $externalSource,
//            $gameId
//        );
//        if ($gameAttacher === null) {
//            // $againstGame replaced by $gameId
//            // $competition = $againstGame->getPoule()->getRound()->getNumber()->getCompetition();
        ////            $competition = $externalCompetition;
        ////            $competitors = array_values($competition->getTeamCompetitors()->toArray());
        ////            $competitorMap = new CompetitorMap($competitors);
        ////            $gameOutput = new AgainstGameOutput($competitorMap, $this->logger);
        ////            $gameOutput->output($againstGame, 'there is no externalId for external source "' . $externalSource->getName() .' and game');
//            throw new \Exception('there is no externalId for external source "' . $externalSource->getName() .'" and external gameid "' . (string)$againstGame->getId() . '"', E_ERROR);
//        }
        $externalGame = $externalSourceCompetitionDetails->getAgainstGame($externalCompetition, $gameId, $removeFromGameCache);
        if ($externalGame === null) {
            throw new \Exception('externalSource "' . $externalSource->getName() .'" could not find a game for id "' . $gameId . '"', E_ERROR);
        }
        return $externalGame;
    }

    public function getSeason(
        ExternalSource\Competitions $externalSourceCompetitions,
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
        $externalSeason = $externalSourceCompetitions->getSeason($seasonAttacher->getExternalId());
        if ($externalSeason === null) {
            throw new \Exception('external source "' . $externalSource->getName() . '" could not find a season for externalId "' . $seasonAttacher->getExternalId() . '"', E_ERROR);
        }
        return $externalSeason;
    }

    public function getAssociation(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource,
        Sport $sport,
        Association $association
    ): Association {
        $externalSport = $this->getSport($externalSourceCompetitions, $externalSource, $sport);
        $associationAttacher = $this->associationAttacherRepos->findOneByImportable(
            $externalSource,
            $association
        );
        if ($associationAttacher === null) {
            throw new \Exception("for external source \"" . $externalSource->getName() ."\" and association \"" . $association->getName() . "\" there is no externalId", E_ERROR);
        }
        $externalAssociation = $externalSourceCompetitions->getAssociation($externalSport, $associationAttacher->getExternalId());
        if ($externalAssociation === null) {
            throw new \Exception("external source \"" . $externalSource->getName() ."\" could not find an externalId for \"" . $associationAttacher->getExternalId() . "\"", E_ERROR);
        }
        return $externalAssociation;
    }

    public function getLeague(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource,
        Sport $sport,
        League $league
    ): League {
        $association = $league->getAssociation();
        $externalAssociation = $this->getAssociation($externalSourceCompetitions, $externalSource, $sport, $association);
        $leagueAttacher = $this->leagueAttacherRepos->findOneByImportable(
            $externalSource,
            $league
        );
        if ($leagueAttacher === null) {
            throw new \Exception('for external source "' . $externalSource->getName() .'" and league "' . $league->getName() . '" there is no externalId', E_ERROR);
        }
        $externalLeague = $externalSourceCompetitions->getLeague($externalAssociation, $leagueAttacher->getExternalId());
        if ($externalLeague === null) {
            throw new \Exception('external source "' . $externalSource->getName() . '" could not find a league for externalId "' . $leagueAttacher->getExternalId() . '"', E_ERROR);
        }
        return $externalLeague;
    }

    public function getCompetition(
        ExternalSource\Competitions $externalSourceCompetitions,
        ExternalSource $externalSource,
        Sport $sport,
        League $league,
        Season $season
    ): Competition {
        $externalSport = $this->getSport($externalSourceCompetitions, $externalSource, $sport);
        $externalLeague = $this->getLeague($externalSourceCompetitions, $externalSource, $sport, $league);
        $externalSeason = $this->getSeason($externalSourceCompetitions, $externalSource, $season);
        $externalCompetition = $externalSourceCompetitions->getCompetition(
            $externalSport,
            $externalLeague,
            $externalSeason
        );
        if ($externalCompetition === null) {
            throw new \Exception("external source \"" . $externalSource->getName() ."\" could not find a competition for sport/league/season \"" . $externalSport->getName() . "\"/\"" . $externalLeague->getName() . "\"/\"" . $externalSeason->getName() . "\"", E_ERROR);
        }
        return $externalCompetition;
    }
}
