<?php

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Sports\Competition\Service as CompetitionService;
use SportsHelpers\Sport\PersistVariant;
use SportsImport\Attachers\LeagueAttacher;
use SportsImport\Attachers\SeasonAttacher;
use SportsImport\Attachers\SportAttacher;
use SportsImport\ExternalSource;
use Sports\Repositories\CompetitionRepository;
use Sports\Competition as CompetitionBase;
use Sports\Competition\Sport as CompetitionSport;
use SportsImport\Attachers\CompetitionAttacher as CompetitionAttacher;
use SportsImport\Repositories\AttacherRepository;

/**
 * @api
 */
final class Competition
{
    /** @var AttacherRepository<SeasonAttacher>  */
    protected AttacherRepository $seasonAttacherRepos;
    /** @var AttacherRepository<CompetitionAttacher>  */
    protected AttacherRepository $competitionAttacherRepos;
    /** @var AttacherRepository<LeagueAttacher>  */
    protected AttacherRepository $leagueAttacherRepos;
    /** @var AttacherRepository<SportAttacher>  */
    protected AttacherRepository $sportAttacherRepos;


    public function __construct(
        protected CompetitionRepository $competitionRepos,
        protected EntityManagerInterface $entityManager
    ) {
        $metadata = $entityManager->getClassMetadata(SeasonAttacher::class);
        $this->seasonAttacherRepos = new AttacherRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(CompetitionAttacher::class);
        $this->competitionAttacherRepos = new AttacherRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(LeagueAttacher::class);
        $this->leagueAttacherRepos = new AttacherRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(SportAttacher::class);
        $this->sportAttacherRepos = new AttacherRepository($entityManager, $metadata);
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
            $this->entityManager->persist($competitionAttacher);
            $this->entityManager->flush();
        } /*else {
            $this->editCompetition($competitionAttacher->getImportable(), $externalSourceCompetition);
        }*/
    }

    protected function createCompetition(ExternalSource $externalSource, CompetitionBase $externalSourceCompetition): ?CompetitionBase
    {
        $attacher = $this->leagueAttacherRepos->findOneByExternalId($externalSource, (string)$externalSourceCompetition->getLeague()->getId());
        $league = $attacher?->getImportable();
        if ($league  === null) {
            return null;
        }

        $attacher = $this->seasonAttacherRepos->findOneByExternalId($externalSource, (string)$externalSourceCompetition->getSeason()->getId());
        $season = $attacher?->getImportable();
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
            $attacher = $this->sportAttacherRepos->findOneByExternalId($externalSource, $externalSportId);
            $sport = $attacher?->getImportable();
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
            new CompetitionSport(
                $sport, $competition,
                $externalCompetitionSport->getDefaultPointsCalculation(),
                $externalCompetitionSport->getDefaultWinPoints(),
                $externalCompetitionSport->getDefaultDrawPoints(),
                $externalCompetitionSport->getDefaultWinPointsExt(),
                $externalCompetitionSport->getDefaultDrawPointsExt(),
                $externalCompetitionSport->getDefaultLosePointsExt(),
                $externalCompetitionSport);
        }
        $this->competitionRepos->customPersist($competition);
        $this->entityManager->persist($competition);
        $this->entityManager->flush();
        return $competition;
    }

//    protected function editCompetition(CompetitionBase $competition, CompetitionBase $externalSourceCompetition): void
//    {
//         $competition->setName($externalSourceCompetition->getName());
//         $this->entityManager->persist($competition);
//         $this->entityManager->flush();
//    }
}
