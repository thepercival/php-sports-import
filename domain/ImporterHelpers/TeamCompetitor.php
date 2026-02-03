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

final class TeamCompetitor
{
    // TeamCompetitor
    protected EntityRepository $teamCompetitorRepos;
    // TeamCompetitorAttacher
    protected EntityRepository $teamCompetitorAttacherRepos;
    // CompetitionAttacherRepository
    protected EntityRepository $competitionAttacherRepos;
    // TeamAttacherRepository
    protected EntityRepository $teamAttacherRepos;

    public function __construct(
        protected LoggerInterface $logger,
        EntityManagerInterface $entityManager,
    ) {
        $metadata = $entityManager->getClassMetadata(TeamCompetitor::class);
        $this->teamCompetitorRepos = new EntityRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(TeamCompetitorAttacher::class);
        $this->teamCompetitorAttacherRepos = new EntityRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(CompetitionAttacher::class);
        $this->competitionAttacherRepos = new EntityRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(TeamAttacher::class);
        $this->teamAttacherRepos = new EntityRepository($entityManager, $metadata);
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
                $this->teamCompetitorAttacherRepos->save($competitorAttacher);
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
        $team = $this->teamAttacherRepos->findImportable(
            $externalSource,
            (string)$externalSourceTeamCompetitor->getTeam()->getId()
        );
        if ($team === null) {
            $location = $externalSourceTeamCompetitor->getStartId();
            throw new \Exception('team not found for teamcompetitor: "' . $location .'"');
        }
        $competition = $this->competitionAttacherRepos->findImportable(
            $externalSource,
            (string)$externalSourceTeamCompetitor->getCompetition()->getId()
        );
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

        $this->teamCompetitorRepos->save($teamCompetitor);
        return $teamCompetitor;
    }

    protected function editTeamCompetitor(TeamCompetitorBase $teamCompetitor, TeamCompetitorBase $externalSourceTeamCompetitor): void
    {
        $teamCompetitor->setPresent($externalSourceTeamCompetitor->getPresent());
        $teamCompetitor->setPublicInfo($externalSourceTeamCompetitor->getPublicInfo());
        $teamCompetitor->setPrivateInfo($externalSourceTeamCompetitor->getPrivateInfo());
        $this->teamCompetitorRepos->save($teamCompetitor);
    }
}
