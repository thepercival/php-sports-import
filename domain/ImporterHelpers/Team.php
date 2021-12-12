<?php

namespace SportsImport\ImporterHelpers;

use Exception;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\ExternalSource;
use Sports\Team\Repository as TeamRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\Attacher\Team as TeamAttacher;
use Psr\Log\LoggerInterface;
use Sports\Team as TeamBase;
use SportsImport\ExternalSource\Team as ExternalSourceTeam;

class Team
{
    public function __construct(
        protected TeamRepository $teamRepos,
        protected TeamAttacherRepository $teamAttacherRepos,
        protected AssociationAttacherRepository $associationAttacherRepos,
        protected LoggerInterface $logger
    ) {
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
                $this->teamAttacherRepos->save($teamAttacher);
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
        $association = $this->associationAttacherRepos->findImportable(
            $externalSource,
            (string)$externalSourceTeam->getAssociation()->getId()
        );
        if ($association === null) {
            return null;
        }
        $team = new TeamBase($association, $externalSourceTeam->getName());
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());

        $this->teamRepos->save($team);
        return $team;
    }

    protected function editTeam(TeamBase $team, TeamBase $externalSourceTeam): void
    {
        $team->setName($externalSourceTeam->getName());
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());
        $this->teamRepos->save($team);
    }

    public function importTeamImage(
        ExternalSource\CompetitionStructure $externalSourceTeam,
        ExternalSource $externalSource,
        TeamBase $team,
        string $localOutputPath,
        int $maxWidth = null
    ): bool {
        $teamExternalId = $this->teamAttacherRepos->findExternalId($externalSource, $team);
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
            if ($maxWidth !== null) {
                // make smaller if greater than maxWidth
            }
            imagepng($im, $localFilePath);
            imagedestroy($im);
            return true;
        } catch (\Exception $e) {
        }
        return false;
    }
}
