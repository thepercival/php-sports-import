<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Sports\Competitor\Team as TeamCompetitor;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Team as TeamBase;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use SportsImport\ExternalSource\Team as ExternalSourceTeam;

class Team extends SofaScoreHelper implements ExternalSourceTeam
{
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
     * @param Competition $competition
     * @return array|TeamBase[]
     */
    public function getTeams(Competition $competition): array
    {
        return array_map( function(TeamCompetitor $teamCompetitor) : TeamBase {
            return $teamCompetitor->getTeam();
        }, $this->parent->getTeamCompetitors($competition));
    }

    public function getTeam(Competition $competition, $id): ?TeamBase
    {
        $competitionTeams = $this->getTeams($competition);
        if (array_key_exists($id, $competitionTeams)) {
            return $competitionTeams[$id];
        }
        return null;
    }

    public function getImageTeam( string $teamExternalId ): string {
        return $this->apiHelper->getTeamImageData( $teamExternalId );
    }
}
