<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\League as LeagueApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\League as LeagueData;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Sports\League as LeagueBase;
use Sports\Association;

/**
 * @template-extends SofaScoreHelper<LeagueBase>
 */
class League extends SofaScoreHelper
{
    public function __construct(
        protected LeagueApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @param Association $association
     * @return array<int|string, LeagueBase>
     */
    public function getLeagues(Association $association): array
    {
        $externalLeagues = $this->apiHelper->getLeagues($association);

        $leagues = [];
        foreach ($externalLeagues as $leagueData) {
            $league = $this->convertToLeague($association, $leagueData);
            $leagues[$leagueData->id] = $league;
        }

        uasort($leagues, function (LeagueBase $league1, LeagueBase $league2): int {
            if (strcmp($league1->getAssociation()->getName(), $league2->getAssociation()->getName()) === 0) {
                return strcmp($league1->getName(), $league2->getName());
            }
            return strcmp($league1->getAssociation()->getName(), $league2->getAssociation()->getName());
        });
        return $leagues;
    }

    public function getLeague(Association $association, string|int $id): LeagueBase|null
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }
        $leagues = $this->getLeagues($association);
        if (array_key_exists($id, $leagues)) {
            return $leagues[$id];
        }
        return null;
    }

    protected function convertToLeague(Association $association, LeagueData $externalLeague): LeagueBase
    {
        if (array_key_exists($externalLeague->id, $this->cache)) {
            return $this->cache[$externalLeague->id];
        }
        $league = new LeagueBase($association, $externalLeague->name);
        $league->setId($externalLeague->id);
        $this->cache[$externalLeague->id] = $league;
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
