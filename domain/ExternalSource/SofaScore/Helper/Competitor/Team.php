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
use Sports\Competition;
use SportsImport\ExternalSource\Competitor\Team as ExternalSourceTeamCompetitor;

class Team extends SofaScoreHelper implements ExternalSourceTeamCompetitor
{
    /**
     * @var array | TeamCompetitorBase[]
     */
    protected $teamCompetitorCache;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->teamCompetitorCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    /**
     * @return array|TeamCompetitorBase[]
     */
    public function getTeamCompetitors(Competition $competition): array
    {
        return array_values($this->getTeamCompetitorsHelper($competition));
    }

    public function getTeamCompetitor(Competition $competition, $id): ?TeamCompetitorBase
    {
        $competitionTeamCompetitors = $this->getTeamCompetitorsHelper($competition);
        if (array_key_exists($id, $competitionTeamCompetitors)) {
            return $competitionTeamCompetitors[$id];
        }
        return null;
    }

    /**
     * @param Competition $competition
     * @return array|TeamCompetitorBase[]
     */
    protected function getTeamCompetitorsHelper(Competition $competition): array
    {
        $competitionTeamCompetitors = [];

        $apiData = $this->apiHelper->getStructureData($competition);

        $apiDataTeamCompetitors = $this->convertExternalSourceTeamCompetitors($apiData);

        $placeNr = 1;
        $pouleNr = 1;
        /** @var stdClass $externalSourceTeamCompetitor */
        foreach ($apiDataTeamCompetitors as $externalSourceTeamCompetitor) {
            if (array_key_exists($externalSourceTeamCompetitor->id, $competitionTeamCompetitors)) {
                continue;
            }
            $newTeamCompetitor = $this->convertToTeamCompetitor($competition, $pouleNr, $placeNr++, $externalSourceTeamCompetitor);
            $competitionTeamCompetitors[$newTeamCompetitor->getId()] = $newTeamCompetitor;
        }
        return $competitionTeamCompetitors;
    }

    /**
     * @param stdClass $apiData
     * @return array|stdClass[]
     */
    protected function convertExternalSourceTeamCompetitors(stdClass $apiData): array
    {
        if (property_exists($apiData, 'teamCompetitors')) {
            return $apiData->teamCompetitors;
        }
        $apiDataTeamCompetitors = [];

        if (!property_exists($apiData, 'standingsTables') || count($apiData->standingsTables) === 0) {
            return $apiDataTeamCompetitors;
        }
        $standingsTables = $apiData->standingsTables[0];
        if (!property_exists($standingsTables, 'tableRows')) {
            return $apiDataTeamCompetitors;
        }
        foreach ($standingsTables->tableRows as $tableRow) {
            if (!property_exists($tableRow, 'team')) {
                continue;
            }
            if( !property_exists($tableRow->team, "id") ) {
                continue;
            }
            if( !property_exists($tableRow, "id") ) {
                continue;
            }
            $apiDataTeamCompetitors[] = $tableRow;
        }
        uasort( $apiDataTeamCompetitors, function( stdClass $a, stdClass $b): int {
            return $a->id < $b->id ? -1 : 1;
        });
        return $apiDataTeamCompetitors;
    }

    protected function convertToTeamCompetitor(
        Competition $competition,
        int $pouleNr, int $placeNr,
        stdClass $externalSourceTeamCompetitor): TeamCompetitorBase
    {
        if( array_key_exists( $externalSourceTeamCompetitor->id, $this->teamCompetitorCache ) ) {
            return $this->teamCompetitorCache[$externalSourceTeamCompetitor->id];
        }
        $association = $competition->getLeague()->getAssociation();
        $team = $this->createTeam( $association, $externalSourceTeamCompetitor->team );
        $teamCompetitor = new TeamCompetitorBase( $competition, $pouleNr, $placeNr, $team );
        $teamCompetitor->setId( $externalSourceTeamCompetitor->id );
        $this->teamCompetitorCache[$teamCompetitor->getId()] = $teamCompetitor;
        return $teamCompetitor;
    }

    protected function createTeam(Association $association, stdClass $externalTeam): TeamBase {
        return $this->apiHelper->convertTeam($association, $externalTeam);
    }
}
