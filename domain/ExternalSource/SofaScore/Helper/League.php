<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-3-18
 * Time: 19:55
 */

namespace Voetbal\ExternalSource\SofaScore\Helper;

use Voetbal\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Voetbal\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Voetbal\League as LeagueBase;
use Voetbal\ExternalSource;
use stdClass;
use Psr\Log\LoggerInterface;
use Voetbal\Import\Service as ImportService;
use Voetbal\ExternalSource\SofaScore;

use Voetbal\ExternalSource\League as ExternalSourceLeague;
use Voetbal\Planning\Game;

class League extends SofaScoreHelper implements ExternalSourceLeague
{
    /**
     * @var LeagueBase[]|null|array
     */
    protected $leagues;

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
     * @return array|LeagueBase[]
     */
    public function getLeagues(): array
    {
        $this->initLeagues();
        return array_values($this->leagues);
    }

    public function getLeague($id = null): ?LeagueBase
    {
        $this->initLeagues();
        if (array_key_exists($id, $this->leagues)) {
            return $this->leagues[$id];
        }
        return null;
    }

    protected function initLeagues()
    {
        if ($this->leagues !== null) {
            return;
        }
        $this->leagues = $this->getInitLeagues($this->getLeagueData());
        uasort($this->leagues, function (LeagueBase $league1, LeagueBase $league2): int {
            if( strcmp( $league1->getAssociation()->getName(), $league2->getAssociation()->getName() ) === 0 ) {
                return strcmp( $league1->getName(), $league2->getName() );
            }
            return strcmp( $league1->getAssociation()->getName(), $league2->getAssociation()->getName() );
        });
    }

    /**
     * @return array | stdClass[]
     */
    protected function getLeagueData(): array
    {
        $sports = $this->parent->getSports();

        $leagueData = [];
        foreach ($sports as $sport) {
            if ($sport->getName() !== SofaScore::SPORTFILTER) {
                continue;
            }
            $apiData = $this->apiHelper->getCompetitionsData($sport);
            $leagueData = array_merge($leagueData, $apiData->sportItem->tournaments);
        }
        return $leagueData;
    }

    /**
     * * {"name":"Premier League 19\/20","slug":"premier-league-1920","year":"19\/20","id":23776}
     *
     * @param array|stdClass[] $competitions
     * @return array|LeagueBase[]
     */
    protected function getInitLeagues(array $competitions): array
    {
        $leagues = [];
        foreach ($competitions as $competition) {
            if ($competition->category === null) {
                continue;
            }
            $association = $this->parent->getAssociation($competition->category->id);
            if ($association === null) {
                continue;
            }
            if ($competition->tournament === null || !property_exists($competition->tournament, "uniqueId")) {
                continue;
            }
            $name = $competition->tournament->name;
            if ($this->hasName($this->leagues, $name)) {
                continue;
            }
            $league = new LeagueBase($association, $name);
            $league->setId($competition->tournament->uniqueId);
            $leagues[$league->getId()] = $league;
        }
        return $leagues;
    }
}
