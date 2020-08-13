<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 6-3-18
 * Time: 19:55
 */

namespace Voetbal\ExternalSource\SofaScore\Helper;

use League\Period\Period;
use stdClass;
use Voetbal\Association as AssociationBase;
use Voetbal\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Voetbal\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Voetbal\Season as SeasonBase;
use Voetbal\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;
use Voetbal\Import\Service as ImportService;
use Voetbal\ExternalSource\Season as ExternalSourceSeason;

class Season extends SofaScoreHelper implements ExternalSourceSeason
{
    /**
     * @var array|SeasonBase[]|null
     */
    protected $seasons;

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
     * @return array|SeasonBase[]
     */
    public function getSeasons(): array
    {
        $this->initSeasons();
        return array_values($this->seasons);
    }

    public function getSeason($id = null): ?SeasonBase
    {
        $this->initSeasons();
        if (array_key_exists($id, $this->seasons)) {
            return $this->seasons[$id];
        }
        return null;
    }

    protected function initSeasons()
    {
        if ($this->seasons !== null) {
            return;
        }
        $this->setSeasons($this->getSeasonData());
    }

    /**
     * @return array|stdClass[]
     */
    protected function getSeasonData(): array
    {
        $sports = $this->parent->getSports();
        $seasonData = [];
        foreach ($sports as $sport) {
            if ($sport->getName() !== SofaScore::SPORTFILTER) {
                continue;
            }
            $apiData = $this->apiHelper->getCompetitionsData($sport);
            $seasonData = array_merge($seasonData, $apiData->sportItem->tournaments);
        }
        return $seasonData;
    }

    /**
     * {"name":"Premier League 19\/20","slug":"premier-league-1920","year":"19\/20","id":23776}
     *
     * @param array|\stdClass[] $competitions
     */
    protected function setSeasons(array $competitions)
    {
        $this->seasons = [];
        foreach ($competitions as $competition) {
            if ($competition->season === null) {
                continue;
            }
            if (strlen($competition->season->year) === 0) {
                continue;
            }
            $name = $this->getName($competition->season->year);
            if ($this->hasName($this->seasons, $name)) {
                continue;
            }
            $season = $this->createSeason($competition->season->year, $name) ;
            $this->seasons[$season->getId()] = $season;
        }
    }

    protected function createSeason($id, $name): SeasonBase
    {
        $season = new SeasonBase($name, $this->getPeriod($name));
        $season->setId($id);
        return $season;
    }

    protected function getName(string $name): string
    {
        if (strpos($name, "/") === false) {
            return $name;
        }
        return "20" . substr($name, 0, 2) . "/" . "20" . substr($name, 3, 2);
    }

    protected function getPeriod(string $name): Period
    {
        $start = null;
        $end = null;
        if (strpos($name, "/") !== false) {
            $year = substr($name, 0, 4);
            $start = $year . "-07-01";
            $year = substr($name, 5, 4);
            $end = $year . "-07-01";
        } else {
            $start = $name . "-01-01";
            $end = (((int)$name)+1) . "-01-01";
        }
        $startDateTime = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:s", $start . "T00:00:00", new \DateTimeZone('UTC'));
        $endDateTime = \DateTimeImmutable::createFromFormat("Y-m-d\TH:i:s", $end . "T00:00:00", new \DateTimeZone('UTC'));
        return new Period($startDateTime, $endDateTime);
    }
}
