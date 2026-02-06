<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Sports\Competitor\StartLocation;
use Sports\Competitor\Team as TeamCompetitorBase;
use SportsImport\Attachers\CompetitionAttacher as CompetitionAttacher;
use SportsImport\Attachers\TeamAttacher as TeamAttacher;
use SportsImport\Attachers\TeamCompetitorAttacher as TeamCompetitorAttacher;
use SportsImport\ExternalSource;
use SportsImport\Repositories\AttacherRepository;

/**
 * @api
 */
final class TeamCompetitor
{
    /** @var EntityRepository<TeamCompetitor> */
//    protected EntityRepository $teamCompetitorRepos;
    /** @var AttacherRepository<TeamCompetitorAttacher> */
    protected AttacherRepository $teamCompetitorAttacherRepos;
    /** @var AttacherRepository<CompetitionAttacher> */
    protected AttacherRepository $competitionAttacherRepos;
    /** @var AttacherRepository<TeamAttacher> */
    protected AttacherRepository $teamAttacherRepos;

    public function __construct(
        protected LoggerInterface $logger,
        protected EntityManagerInterface $entityManager,
    ) {
//        $metadata = $entityManager->getClassMetadata(TeamCompetitor::class);
//        $this->teamCompetitorRepos = new EntityRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(TeamCompetitorAttacher::class);
        $this->teamCompetitorAttacherRepos = new AttacherRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(CompetitionAttacher::class);
        $this->competitionAttacherRepos = new AttacherRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(TeamAttacher::class);
        $this->teamAttacherRepos = new AttacherRepository($entityManager, $metadata);
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<TeamCompetitorBase> $externalSourceTeamCompetitors
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceTeamCompetitors): void
    {
        $updated = 0;
        $added = 0;
        foreach ($externalSourceTeamCompetitors as $externalSourceTeamCompetitor) {
            $externalId = $externalSourceTeamCompetitor->getId();
            if ($externalId === null) {
                continue;
            }
            $competitorAttacher = $this->teamCompetitorAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
            );
            if ($competitorAttacher === null) {
                $teamCompetitor = $this->createTeamCompetitor($externalSource, $externalSourceTeamCompetitor);
                if ($teamCompetitor === null) {
                    continue;
                }
                $competitorAttacher = new TeamCompetitorAttacher(
                    $teamCompetitor,
                    $externalSource,
                    (string)$externalId
                );
                $this->entityManager->persist($competitorAttacher);
                $this->entityManager->flush();
                $added++;
            } else {
                $this->editTeamCompetitor($competitorAttacher->getImportable(), $externalSourceTeamCompetitor);
                $updated++;
            }
        }
        $this->logger->info("added: " . $added . ", updated: " . $updated);
    }

    protected function createTeamCompetitor(ExternalSource $externalSource, TeamCompetitorBase $externalSourceTeamCompetitor): ?TeamCompetitorBase
    {
        $attacher = $this->teamAttacherRepos->findOneByExternalId($externalSource, (string)$externalSourceTeamCompetitor->getTeam()->getId());
        $team = $attacher?->getImportable();

        if ($team === null) {
            $location = $externalSourceTeamCompetitor->getStartId();
            throw new \Exception('team not found for teamcompetitor: "' . $location .'"');
        }
        $attacher = $this->competitionAttacherRepos->findOneByExternalId(
            $externalSource,
            (string)$externalSourceTeamCompetitor->getCompetition()->getId()
        );
        $competition = $attacher?->getImportable();
        if ($competition === null) {
            return null;
        }
        $singleCategory = $competition->getSingleCategory();
        $teamCompetitor = new TeamCompetitorBase(
            $competition,
            new StartLocation(
                $singleCategory->getNumber(),
                $externalSourceTeamCompetitor->getPouleNr(),
                $externalSourceTeamCompetitor->getPlaceNr()
            ),
            $team,
        );
        $teamCompetitor->setPresent($externalSourceTeamCompetitor->getPresent());
        $teamCompetitor->setPublicInfo($externalSourceTeamCompetitor->getPublicInfo());
        $teamCompetitor->setPrivateInfo($externalSourceTeamCompetitor->getPrivateInfo());

        $this->entityManager->persist($teamCompetitor);
        $this->entityManager->flush();
        return $teamCompetitor;
    }

    protected function editTeamCompetitor(TeamCompetitorBase $teamCompetitor, TeamCompetitorBase $externalSourceTeamCompetitor): void
    {
        $teamCompetitor->setPresent($externalSourceTeamCompetitor->getPresent());
        $teamCompetitor->setPublicInfo($externalSourceTeamCompetitor->getPublicInfo());
        $teamCompetitor->setPrivateInfo($externalSourceTeamCompetitor->getPrivateInfo());
        $this->entityManager->persist($teamCompetitor);
        $this->entityManager->flush();
    }
}
