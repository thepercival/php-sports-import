<?php

namespace SportsImport\ExternalSource\SofaScore\Helper\Competitor;

use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Competitor\Team as TeamCompetitorBase;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use SportsImport\ExternalSource\Competitor\Team as ExternalSourceTeamCompetitor;

class Team extends SofaScoreHelper implements ExternalSourceTeamCompetitor
{
    /**
     * @var array|TeamCompetitorBase[]|null
     */
    protected $teamCompetitors = [];

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
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
            return $this->teamCompetitors[$id];
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
        $association = $competition->getLeague()->getAssociation();

        $apiData = $this->apiHelper->getStructureData($competition);

        $apiDataTeamCompetitors = $this->convertExternalSourceTeamCompetitors($apiData);

        // @TODO DEPRECATED
//        /** @var stdClass $externalSourceTeamCompetitor */
//        foreach ($apiDataTeamCompetitors as $externalSourceTeamCompetitor) {
//
////            if( $externalSourceTeamCompetitor->tournament === null || !property_exists($externalSourceTeamCompetitor->tournament, "uniqueId") ) {
////                continue;
////            }
//            if (array_key_exists($externalSourceTeamCompetitor->id, $this->teamCompetitors)) {
//                $teamCompetitor = $this->teamCompetitors[$externalSourceTeamCompetitor->id];
//                $competitionTeamCompetitors[$teamCompetitor->getId()] = $teamCompetitor;
//                continue;
//            }
//
//            $newTeamCompetitor = $this->apiHelper->convertTeamCompetitor($association, $externalSourceTeamCompetitor);
//            $this->teamCompetitors[$newTeamCompetitor->getId()] = $newTeamCompetitor;
//            $competitionTeamCompetitors[$newTeamCompetitor->getId()] = $newTeamCompetitor;
//        }
        return $competitionTeamCompetitors;
    }

    protected function convertExternalSourceTeamCompetitors($apiData)
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
            if (!property_exists($tableRow, 'teamCompetitor')) {
                continue;
            }
            $apiDataTeamCompetitors[] = $tableRow->teamCompetitor;
        }
        return $apiDataTeamCompetitors;
    }
}
