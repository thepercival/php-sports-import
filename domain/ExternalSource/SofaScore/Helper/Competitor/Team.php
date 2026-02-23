<?php

namespace SportsImport\ExternalSource\SofaScore\Helper\Competitor;

use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Competitor\StartLocation;
use Sports\Competitor\Team as TeamCompetitorBase;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelpers\TeamCompetitorApiHelper as TeamCompetitorApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\TeamCompetitor as TeamCompetitorData;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Team as TeamHelper;

/**
 * @template-extends SofaScoreHelper<TeamCompetitorBase>
 */
final class Team extends SofaScoreHelper
{
    public function __construct(
        protected TeamHelper $teamHelper,
        protected TeamCompetitorApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @return list<TeamCompetitorBase>
     */
    public function getTeamCompetitors(Competition $competition): array
    {
        return array_values($this->getTeamCompetitorsHelper($competition));
    }

    public function getTeamCompetitor(Competition $competition, string|int $id): ?TeamCompetitorBase
    {
        $competitionTeamCompetitors = $this->getTeamCompetitorsHelper($competition);
        if (array_key_exists($id, $competitionTeamCompetitors)) {
            return $competitionTeamCompetitors[$id];
        }
        return null;
    }

    /**
     * @param Competition $competition
     * @return array<int|string, TeamCompetitorBase>
     */
    protected function getTeamCompetitorsHelper(Competition $competition): array
    {
        $competitionTeamCompetitors = [];

        $teamCompetitorsData = $this->apiHelper->getTeamCompetitors($competition);

        $placeNr = 1;
        $pouleNr = 1;
        foreach ($teamCompetitorsData as $teamCompetitorData) {
            if (array_key_exists($teamCompetitorData->id, $competitionTeamCompetitors)) {
                continue;
            }
            $newTeamCompetitor = $this->convertDataToTeamCompetitor($competition, $pouleNr, $placeNr++, $teamCompetitorData);
            $competitionTeamCompetitors[$teamCompetitorData->id] = $newTeamCompetitor;
        }
        return $competitionTeamCompetitors;
    }

    protected function convertDataToTeamCompetitor(
        Competition $competition,
        int $pouleNr,
        int $placeNr,
        TeamCompetitorData $externalTeamCompetitor
    ): TeamCompetitorBase {
        if (array_key_exists($externalTeamCompetitor->id, $this->cache)) {
            return $this->cache[$externalTeamCompetitor->id];
        }
        $association = $competition->getLeague()->getAssociation();
        $team = $this->teamHelper->convertDataToTeam($association, $externalTeamCompetitor->team);
        $startLocation = new StartLocation(1, $pouleNr, $placeNr);
        $teamCompetitor = new TeamCompetitorBase($competition, $startLocation, $team);
        $teamCompetitor->setId($externalTeamCompetitor->id);
        $this->cache[$externalTeamCompetitor->id] = $teamCompetitor;
        return $teamCompetitor;
    }
}
