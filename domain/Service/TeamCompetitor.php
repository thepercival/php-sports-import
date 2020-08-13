<?php

namespace SportsImport\Service;

use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\ImporterInterface;
use SportsImport\ExternalSource;
use Sports\Competitor\Team\Repository as TeamCompetitorRepository;
use SportsImport\Attacher\Competitor\Team\Repository as TeamCompetitorAttacherRepository;
use Sports\Competitor as CompetitorBase;
use SportsImport\Attacher\Competitor\Team as TeamCompetitorAttacher;
use Psr\Log\LoggerInterface;
use Sports\Competitor\Team as TeamCompetitorBase;

class TeamCompetitor implements ImporterInterface
{
    /**
     * @var TeamCompetitorRepository
     */
    protected $teamCompetitorRepos;
    /**
     * @var TeamCompetitorAttacherRepository
     */
    protected $teamCompetitorAttacherRepos;
    /**
     * @var CompetitionAttacherRepository
     */
    protected $competitionAttacherRepos;
    /**
     * @var TeamAttacherRepository
     */
    protected $teamAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        TeamCompetitorRepository $teamCompetitorRepos,
        TeamCompetitorAttacherRepository $teamCompetitorAttacherRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        TeamAttacherRepository $teamAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->teamCompetitorRepos = $teamCompetitorRepos;
        $this->teamCompetitorAttacherRepos = $teamCompetitorAttacherRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
        $this->teamAttacherRepos = $teamAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|TeamCompetitorBase[] $externalSourceTeamCompetitors
     * @throws \Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceTeamCompetitors)
    {
        foreach ($externalSourceTeamCompetitors as $externalSourceTeamCompetitor) {
            $externalId = $externalSourceTeamCompetitor->getId();
            $competitorAttacher = $this->teamCompetitorAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($competitorAttacher === null) {
                $teamCompetitor = $this->createTeamCompetitor($externalSource, $externalSourceTeamCompetitor);
                if ($teamCompetitor === null) {
                    continue;
                }
                $competitorAttacher = new TeamCompetitorAttacher(
                    $teamCompetitor,
                    $externalSource,
                    $externalId
                );
                $this->teamCompetitorAttacherRepos->save($competitorAttacher);
            } else {
                $this->editTeamCompetitor($competitorAttacher->getImportable(), $externalSourceTeamCompetitor);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createTeamCompetitor(ExternalSource $externalSource, TeamCompetitorBase $externalSourceTeamCompetitor): ?TeamCompetitorBase
    {
        $team = $this->teamAttacherRepos->findImportable(
            $externalSource,
            $externalSourceTeamCompetitor->getTeam()->getId()
        );
        if ($team === null) {
            return null;
        }
        $competition = $this->competitionAttacherRepos->findImportable(
            $externalSource,
            $externalSourceTeamCompetitor->getTeam()->getAssociation()->getId()
        );
        if ($competition === null) {
            return null;
        }
        $teamCompetitor = new TeamCompetitorBase(
            $competition,
            $externalSourceTeamCompetitor->getPouleNr(),
            $externalSourceTeamCompetitor->getPlaceNr(),
            $team,
        );
        $teamCompetitor->setRegistered($externalSourceTeamCompetitor->getRegistered());
        $teamCompetitor->setInfo($externalSourceTeamCompetitor->getInfo());

        $this->teamCompetitorRepos->save($teamCompetitor);
        return $teamCompetitor;
    }

    protected function editTeamCompetitor(TeamCompetitorBase $teamCompetitor, TeamCompetitorBase $externalSourceTeamCompetitor)
    {
        $teamCompetitor->setRegistered($externalSourceTeamCompetitor->getRegistered());
        $teamCompetitor->setInfo($externalSourceTeamCompetitor->getInfo());
        $this->teamCompetitorRepos->save($teamCompetitor);
    }
}
