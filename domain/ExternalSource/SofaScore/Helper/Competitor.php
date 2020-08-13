<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-3-18
 * Time: 19:55
 */

namespace Voetbal\ExternalSource\SofaScore\Helper;

use stdClass;
use Voetbal\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Voetbal\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Voetbal\Competitor as CompetitorBase;
use Psr\Log\LoggerInterface;
use Voetbal\Import\Service as ImportService;
use Voetbal\ExternalSource\SofaScore;
use \Voetbal\Competition;
use Voetbal\ExternalSource\Competitor as ExternalSourceCompetitor;

class Competitor extends SofaScoreHelper implements ExternalSourceCompetitor
{
    /**
     * @var array|CompetitorBase[]|null
     */
    protected $competitors = [];

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

    public function getCompetitors(Competition $competition): array
    {
        return array_values($this->getCompetitorsHelper($competition));
    }

    public function getCompetitor(Competition $competition, $id): ?CompetitorBase
    {
        $competitionCompetitors = $this->getCompetitorsHelper($competition);
        if (array_key_exists($id, $competitionCompetitors)) {
            return $this->competitors[$id];
        }
        return null;
    }

    /**
     * @param Competition $competition
     * @return array|CompetitorBase[]
     */
    protected function getCompetitorsHelper(Competition $competition): array
    {
        $competitionCompetitors = [];
        $association = $competition->getLeague()->getAssociation();

        $apiData = $this->apiHelper->getCompetitionData($competition);

        $apiDataTeams = $this->convertExternalSourceCompetitors($apiData);

        /** @var stdClass $externalSourceCompetitor */
        foreach ($apiDataTeams as $externalSourceCompetitor) {

//            if( $externalSourceCompetitor->tournament === null || !property_exists($externalSourceCompetitor->tournament, "uniqueId") ) {
//                continue;
//            }
            if (array_key_exists($externalSourceCompetitor->id, $this->competitors)) {
                $competitor = $this->competitors[$externalSourceCompetitor->id];
                $competitionCompetitors[$competitor->getId()] = $competitor;
                continue;
            }

            $newCompetitor = $this->apiHelper->convertCompetitor($association, $externalSourceCompetitor);
            $this->competitors[$newCompetitor->getId()] = $newCompetitor;
            $competitionCompetitors[$newCompetitor->getId()] = $newCompetitor;
        }
        return $competitionCompetitors;
    }

    protected function convertExternalSourceCompetitors($apiData)
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
