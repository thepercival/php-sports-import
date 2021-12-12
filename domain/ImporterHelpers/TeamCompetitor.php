<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Exception;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\ExternalSource;
use Sports\Competitor\Team\Repository as TeamCompetitorRepository;
use SportsImport\Attacher\Competitor\Team\Repository as TeamCompetitorAttacherRepository;
use Sports\Competitor as CompetitorBase;
use SportsImport\Attacher\Competitor\Team as TeamCompetitorAttacher;
use Psr\Log\LoggerInterface;
use Sports\Competitor\Team as TeamCompetitorBase;

class TeamCompetitor
{
    public function __construct(
        protected TeamCompetitorRepository $teamCompetitorRepos,
        protected TeamCompetitorAttacherRepository $teamCompetitorAttacherRepos,
        protected CompetitionAttacherRepository $competitionAttacherRepos,
        protected TeamAttacherRepository $teamAttacherRepos,
        protected LoggerInterface $logger
    ) {
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
            throw new \Exception('team not found for teamcompetitor: "' . $externalSourceTeamCompetitor->getRoundLocationId() .'"');
        }
        $competition = $this->competitionAttacherRepos->findImportable(
            $externalSource,
            (string)$externalSourceTeamCompetitor->getCompetition()->getId()
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

    protected function editTeamCompetitor(TeamCompetitorBase $teamCompetitor, TeamCompetitorBase $externalSourceTeamCompetitor): void
    {
        $teamCompetitor->setRegistered($externalSourceTeamCompetitor->getRegistered());
        $teamCompetitor->setInfo($externalSourceTeamCompetitor->getInfo());
        $this->teamCompetitorRepos->save($teamCompetitor);
    }
}
