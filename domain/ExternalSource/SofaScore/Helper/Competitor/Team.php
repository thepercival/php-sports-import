<?php

namespace SportsImport\ExternalSource\SofaScore\Helper\Competitor;

use Sports\Association;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Competitor\Team as TeamCompetitorBase;
use Sports\Team as TeamBase;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\Data\TeamCompetitor as TeamCompetitorData;
use SportsImport\ExternalSource\SofaScore\Data\Team as TeamData;
use Sports\Competition;
use SportsImport\ExternalSource\Competitor\Team as ExternalSourceTeamCompetitor;

/**
 * @template-extends SofaScoreHelper<TeamCompetitorBase>
 */
class Team extends SofaScoreHelper implements ExternalSourceTeamCompetitor
{

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

        $apiData = $this->apiHelper->getStructureData($competition);

        $apiDataTeamCompetitors = $this->convertExternalTeamCompetitors($apiData);

        $placeNr = 1;
        $pouleNr = 1;
        foreach ($apiDataTeamCompetitors as $externalTeamCompetitor) {
            if (array_key_exists($externalTeamCompetitor->id, $competitionTeamCompetitors)) {
                continue;
            }
            $newTeamCompetitor = $this->convertToTeamCompetitor($competition, $pouleNr, $placeNr++, $externalTeamCompetitor);
            $competitionTeamCompetitors[$externalTeamCompetitor->id] = $newTeamCompetitor;
        }
        return $competitionTeamCompetitors;
    }

    /**
     * @param stdClass $apiData
     * @return list<TeamCompetitorData>
     */
    protected function convertExternalTeamCompetitors(stdClass $apiData): array
    {
        if (property_exists($apiData, 'teamCompetitors')) {
            /** @var list<TeamCompetitorData> $apiDataTeamCompetitors */
            $apiDataTeamCompetitors = $apiData->teamCompetitors;
            return $apiDataTeamCompetitors;
        }
        /** @var list<TeamCompetitorData> $apiDataTeamCompetitors */
        $apiDataTeamCompetitors = [];

        if (!property_exists($apiData, 'standingsTables')) {
            return $apiDataTeamCompetitors;
        }
        if (!is_array($apiData->standingsTables) || count($apiData->standingsTables) === 0) {
            return $apiDataTeamCompetitors;
        }

        /** @var stdClass $standingsTables */
        $standingsTables = $apiData->standingsTables[0];
        if (!property_exists($standingsTables, 'tableRows')) {
            return $apiDataTeamCompetitors;
        }
        /** @var list<TeamCompetitorData> $tableRows */
        $tableRows = $standingsTables->tableRows;
        foreach ($tableRows as $tableRow) {
            if (!property_exists($tableRow, 'team')) {
                continue;
            }
            if (!property_exists($tableRow->team, "id")) {
                continue;
            }
            if (!property_exists($tableRow, "id")) {
                continue;
            }
            $apiDataTeamCompetitors[] = $tableRow;
        }
        uasort($apiDataTeamCompetitors, function (TeamCompetitorData $a, TeamCompetitorData $b): int {
            return $a->id < $b->id ? -1 : 1;
        });
        return array_values($apiDataTeamCompetitors);
    }

    protected function convertToTeamCompetitor(
        Competition $competition,
        int $pouleNr,
        int $placeNr,
        TeamCompetitorData $externalTeamCompetitor
    ): TeamCompetitorBase {
        if (array_key_exists($externalTeamCompetitor->id, $this->cache)) {
            return $this->cache[$externalTeamCompetitor->id];
        }
        $association = $competition->getLeague()->getAssociation();
        $team = $this->createTeam($association, $externalTeamCompetitor->team);
        $teamCompetitor = new TeamCompetitorBase($competition, $pouleNr, $placeNr, $team);
        $teamCompetitor->setId($externalTeamCompetitor->id);
        $this->cache[$externalTeamCompetitor->id] = $teamCompetitor;
        return $teamCompetitor;
    }

    protected function createTeam(Association $association, TeamData $externalTeam): TeamBase
    {
        return $this->apiHelper->convertTeam($association, $externalTeam);
    }
}
