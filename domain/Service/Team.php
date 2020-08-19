<?php

namespace SportsImport\Service;

use \Exception;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\ExternalSource;
use Sports\Team\Repository as TeamRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\Attacher\Team as TeamAttacher;
use Psr\Log\LoggerInterface;
use Sports\Team as TeamBase;

class Team
{
    /**
     * @var TeamRepository
     */
    protected $teamRepos;
    /**
     * @var TeamAttacherRepository
     */
    protected $teamAttacherRepos;
    /**
     * @var AssociationAttacherRepository
     */
    protected $associationAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        TeamRepository $teamRepos,
        TeamAttacherRepository $teamAttacherRepos,
        AssociationAttacherRepository $associationAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->teamRepos = $teamRepos;
        $this->teamAttacherRepos = $teamAttacherRepos;
        $this->associationAttacherRepos = $associationAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|TeamBase[] $externalSourceTeams
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceTeams)
    {
        $updated = 0; $added = 0;
        foreach ($externalSourceTeams as $externalSourceTeam) {
            $externalId = $externalSourceTeam->getId();
            $teamAttacher = $this->teamAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($teamAttacher === null) {
                $team = $this->createTeam($externalSource, $externalSourceTeam);
                if ($team === null) {
                    continue;
                }
                $teamAttacher = new TeamAttacher(
                    $team,
                    $externalSource,
                    $externalId
                );
                $this->teamAttacherRepos->save($teamAttacher);
                $added++;
            } else {
                $this->editTeam($teamAttacher->getImportable(), $externalSourceTeam);
                $updated++;
            }
        }
        $this->logger->info("added: " . $added . ", updated: " . $updated );
    }

    protected function createTeam(ExternalSource $externalSource, TeamBase $externalSourceTeam): ?TeamBase
    {
        $association = $this->associationAttacherRepos->findImportable(
            $externalSource,
            $externalSourceTeam->getAssociation()->getId()
        );
        if ($association === null) {
            return null;
        }
        $team = new TeamBase($association, $externalSourceTeam->getName());
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());
        $team->setImageUrl($externalSourceTeam->getImageUrl());

        $this->teamRepos->save($team);
        return $team;
    }

    protected function editTeam(TeamBase $team, TeamBase $externalSourceTeam)
    {
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());
        $team->setImageUrl($externalSourceTeam->getImageUrl());
        $this->teamRepos->save($team);
    }
}
