<?php

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use SportsImport\Attachers\AssociationAttacher;
use SportsImport\ExternalSource;
use SportsImport\Attachers\TeamAttacher;
use Psr\Log\LoggerInterface;
use Sports\Team as TeamBase;
use SportsImport\Repositories\AttacherRepository;

/**
 * @api
 */
final class Team
{
    /** @var AttacherRepository<TeamAttacher>  */
    protected AttacherRepository $teamAttacherRepos;
    /** @var AttacherRepository<AssociationAttacher>  */
    protected AttacherRepository $associationAttacherRepos;

    public function __construct(
        protected LoggerInterface $logger,
        protected EntityManagerInterface $entityManager,
    ) {
        $metaData = $entityManager->getClassMetadata(TeamAttacher::class);
        $this->teamAttacherRepos = new AttacherRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(AssociationAttacher::class);
        $this->associationAttacherRepos = new AttacherRepository($entityManager, $metaData);
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<TeamBase> $externalSourceTeams
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceTeams): void
    {
        $updated = 0;
        $added = 0;
        foreach ($externalSourceTeams as $externalSourceTeam) {
            $externalId = $externalSourceTeam->getId();
            if ($externalId === null) {
                continue;
            }
            $teamAttacher = $this->teamAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
            );
            if ($teamAttacher === null) {
                $team = $this->createTeam($externalSource, $externalSourceTeam);
                if ($team === null) {
                    continue;
                }
                $teamAttacher = new TeamAttacher(
                    $team,
                    $externalSource,
                    (string)$externalId
                );
                $this->entityManager->persist($teamAttacher);
                $this->entityManager->flush();
                $added++;
            } else {
                $this->editTeam($teamAttacher->getImportable(), $externalSourceTeam);
                $updated++;
            }
        }
        $this->logger->info("added: " . $added . ", updated: " . $updated);
    }

    protected function createTeam(ExternalSource $externalSource, TeamBase $externalSourceTeam): ?TeamBase
    {
        $attacher = $this->associationAttacherRepos->findOneByExternalId($externalSource, (string)$externalSourceTeam->getAssociation()->getId());
        $association = $attacher?->getImportable();

        if ($association === null) {
            return null;
        }
        $team = new TeamBase($association, $externalSourceTeam->getName());
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());

        $this->entityManager->persist($team);
        $this->entityManager->flush();
        return $team;
    }

    protected function editTeam(TeamBase $team, TeamBase $externalSourceTeam): void
    {
        $team->setName($externalSourceTeam->getName());
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }

    /**
     * @psalm-suppress UnusedParam
     */
    public function importTeamImage(
        ExternalSource\CompetitionStructure $externalSourceTeam,
        ExternalSource $externalSource,
        TeamBase $team,
        string $localOutputPath,
        int|null $maxWidth = null
    ): bool {
        $teamExternalId = $this->teamAttacherRepos->findOneByImportable($externalSource, $team)?->getExternalId();
        if ($teamExternalId === null) {
            return false;
        }
        $localFilePath = $localOutputPath . (string)$team->getId() . ".png";

        if (file_exists($localFilePath)) {
            $timestamp = filectime($localFilePath);
            $modifyDate = null;
            if ($timestamp !== false) {
                $modifyDate = new \DateTimeImmutable('@' . $timestamp);
            }
            if ($modifyDate !== null && $modifyDate->modify("+1 years") > (new \DateTimeImmutable())) {
                return false;
            }
        }

        try {
            $imgStream = $externalSourceTeam->getImageTeam($teamExternalId);
            $im = imagecreatefromstring($imgStream);
            if ($im === false) {
                return false;
            }
//            if ($maxWidth !== null) {
//                // make smaller if greater than maxWidth
//            }
            imagepng($im, $localFilePath);
            imagedestroy($im);
            return true;
        } catch (\Exception $e) {
        }
        return false;
    }
}
