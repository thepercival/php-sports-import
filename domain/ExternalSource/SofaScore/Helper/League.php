<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\League as LeagueBase;
use Sports\Association;
use stdClass;
use Psr\Log\LoggerInterface;
use SportsImport\Service as ImportService;
use SportsImport\ExternalSource\SofaScore;

use SportsImport\ExternalSource\League as ExternalSourceLeague;

class League extends SofaScoreHelper implements ExternalSourceLeague
{
    /**
     * @var array | LeagueBase[]
     */
    protected $leagueCache;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->leagueCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    /**
     * @param Association $association
     * @return array|LeagueBase[]
     */
    public function getLeagues( Association $association ): array
    {
        $externalLeagues = $this->apiHelper->getLeaguesData($association);

        $leagues = [];
        foreach( $externalLeagues as $externalLeague ) {
            $league = $this->convertLeague( $association, $externalLeague );
            $leagues[$league->getId()] = $league;
        }

        uasort($leagues, function (LeagueBase $league1, LeagueBase $league2): int {
            if( strcmp( $league1->getAssociation()->getName(), $league2->getAssociation()->getName() ) === 0 ) {
                return strcmp( $league1->getName(), $league2->getName() );
            }
            return strcmp( $league1->getAssociation()->getName(), $league2->getAssociation()->getName() );
        });
        return $leagues;
    }

    public function getLeague(Association $association, $id = null): ?LeagueBase
    {
        if (array_key_exists($id, $this->leagueCache)) {
            return $this->leagueCache[$id];
        }
        $leagues = $this->getLeagues( $association );
        if (array_key_exists($id, $leagues)) {
            return $leagues[$id];
        }
        return null;
    }

    protected function convertLeague(Association $association, stdClass $externalLeague ): LeagueBase
    {
        if( array_key_exists( $externalLeague->id, $this->leagueCache ) ) {
            return $this->leagueCache[$externalLeague->id];
        }
        $name = $externalLeague->name;
        $league = new LeagueBase($association, $name);
        $league->setId($externalLeague->id);
        $this->leagueCache[$league->getId()] = $league;
        return $league;
    }

    //    public function getLeague($id = null): ?LeagueBase
//    {
//        $this->initLeagues();
//        if (array_key_exists($id, $this->leagues)) {
//            return $this->leagues[$id];
//        }
//        return null;
//    }
}
