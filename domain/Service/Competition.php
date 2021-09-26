<?php

namespace SportsImport\Service;

use Sports\Competition\Service as CompetitionService;
use SportsHelpers\Sport\PersistVariant;
use SportsImport\ExternalSource;
use Sports\Competition\Repository as CompetitionRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use Sports\Competition as CompetitionBase;
use Sports\Competition\Sport as CompetitionSport;
use SportsImport\Attacher\Competition as CompetitionAttacher;
use Psr\Log\LoggerInterface;

class Competition
{
    public function __construct(
        protected CompetitionRepository $competitionRepos,
        protected CompetitionAttacherRepository $competitionAttacherRepos,
        protected LeagueAttacherRepository $leagueAttacherRepos,
        protected SeasonAttacherRepository $seasonAttacherRepos,
        protected SportAttacherRepository $sportAttacherRepos
    ) {
    }

    /**
     * @param ExternalSource $externalSource
     * @param CompetitionBase $externalSourceCompetition
     * @throws \Exception
     */
    public function import(ExternalSource $externalSource, CompetitionBase $externalSourceCompetition): void
    {
        $externalId = $externalSourceCompetition->getId();
        if ($externalId === null) {
            return;
        }
        $competitionAttacher = $this->competitionAttacherRepos->findOneByExternalId(
            $externalSource,
            (string)$externalId
        );
        if ($competitionAttacher === null) {
            $competition = $this->createCompetition($externalSource, $externalSourceCompetition);
            if ($competition === null) {
                return;
            }
            $competitionAttacher = new CompetitionAttacher(
                $competition,
                $externalSource,
                (string)$externalId
            );
            $this->competitionAttacherRepos->save($competitionAttacher);
        } else {
            $this->editCompetition($competitionAttacher->getImportable(), $externalSourceCompetition);
        }
    }

    protected function createCompetition(ExternalSource $externalSource, CompetitionBase $externalSourceCompetition): ?CompetitionBase
    {
        $league = $this->leagueAttacherRepos->findImportable(
            $externalSource,
            (string)$externalSourceCompetition->getLeague()->getId()
        );
        if ($league  === null) {
            return null;
        }
        $season = $this->seasonAttacherRepos->findImportable(
            $externalSource,
            (string)$externalSourceCompetition->getSeason()->getId()
        );
        if ($season  === null) {
            return null;
        }
        $existingCompetition = $this->competitionRepos->findOneBy([
            "league" => $league, "season" => $season
        ]);
        if ($existingCompetition !== null) {
            return $existingCompetition;
        }

        $competition = new CompetitionBase($league, $season);
        $competition->setStartDateTime($season->getStartDateTime());

        foreach ($externalSourceCompetition->getSports() as $externalCompetitionSport) {
            $externalSportId = (string)$externalCompetitionSport->getSport()->getId();
            $sport = $this->sportAttacherRepos->findImportable($externalSource, $externalSportId);
            if ($sport === null) {
                continue;
            }
            /*$sportPersistVariant = new PersistVariant(
                $sport->getDefaultGameMode(),
                $sport->getDefaultNrOfSidePlaces(),
                $sport->getDefaultNrOfSidePlaces(),
                $nrOfGamePlaces,
                $nrOfH2H,
                $nrOfGamesPerPlace
            );*/
            new CompetitionSport($sport, $competition, $externalCompetitionSport);
        }
        $this->competitionRepos->customPersist($competition);
        $this->competitionRepos->save($competition);
        return $competition;
    }

    protected function editCompetition(CompetitionBase $competition, CompetitionBase $externalSourceCompetition): void
    {
        // $competition->setName($externalSourceCompetition->getName());
        // $this->competitionRepos->save($competition);
    }
}
