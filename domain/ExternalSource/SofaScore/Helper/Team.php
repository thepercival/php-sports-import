<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Team as TeamBase;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use SportsImport\ExternalSource\Team as ExternalSourceTeam;

class Team extends SofaScoreHelper implements ExternalSourceTeam
{
    /**
     * @var array|TeamBase[]|null
     */
    protected $teams = [];

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

    public function getTeams(Competition $competition): array
    {
        return array_values($this->getTeamsHelper($competition));
    }

    public function getTeam(Competition $competition, $id): ?TeamBase
    {
        $competitionTeams = $this->getTeamsHelper($competition);
        if (array_key_exists($id, $competitionTeams)) {
            return $this->teams[$id];
        }
        return null;
    }

    /**
     * @param Competition $competition
     * @return array|TeamBase[]
     */
    protected function getTeamsHelper(Competition $competition): array
    {
        $competitionTeams = [];
        $association = $competition->getLeague()->getAssociation();

        $apiData = $this->apiHelper->getStructureData($competition);

        $apiDataTeams = $this->convertExternalSourceTeams($apiData);

        // @TODO DEPRECATED
//        /** @var stdClass $externalSourceTeam */
//        foreach ($apiDataTeams as $externalSourceTeam) {
//
////            if( $externalSourceTeam->tournament === null || !property_exists($externalSourceTeam->tournament, "uniqueId") ) {
////                continue;
////            }
//            if (array_key_exists($externalSourceTeam->id, $this->teams)) {
//                $team = $this->teams[$externalSourceTeam->id];
//                $competitionTeams[$team->getId()] = $team;
//                continue;
//            }
//
//            $newTeam = $this->apiHelper->convertTeam($association, $externalSourceTeam);
//            $this->teams[$newTeam->getId()] = $newTeam;
//            $competitionTeams[$newTeam->getId()] = $newTeam;
//        }
        return $competitionTeams;
    }

    protected function convertExternalSourceTeams($apiData)
    {
        if (property_exists($apiData, 'teams')) {
            return $apiData->teams;
        }
        $apiDataTeams = [];

        if (!property_exists($apiData, 'standingsTables') || count($apiData->standingsTables) === 0) {
            return $apiDataTeams;
        }
        $standingsTables = $apiData->standingsTables[0];
        if (!property_exists($standingsTables, 'tableRows')) {
            return $apiDataTeams;
        }
        foreach ($standingsTables->tableRows as $tableRow) {
            if (!property_exists($tableRow, 'team')) {
                continue;
            }
            $apiDataTeams[] = $tableRow->team;
        }
        return $apiDataTeams;
    }
}
