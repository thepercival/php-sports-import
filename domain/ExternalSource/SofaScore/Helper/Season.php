<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use League\Period\Period;
use Sports\Sport;
use stdClass;
use Sports\Association as AssociationBase;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Season as SeasonBase;
use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;
use SportsImport\Import\Service as ImportService;
use SportsImport\ExternalSource\Season as ExternalSourceSeason;

class Season extends SofaScoreHelper implements ExternalSourceSeason
{
    /**
     * @var array|SeasonBase[]
     */
    protected $seasonCache;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->seasonCache = [];
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
        $seasons = [];
        $externalSeasons = $this->apiHelper->getSeasonsData();

        foreach ($externalSeasons as $externalSeason) {
            $season = $this->convertSeason($externalSeason);
            $seasons[$season->getId()] = $season;
        }
        return $seasons;
    }

    public function getSeason($id = null): ?SeasonBase
    {
        if (array_key_exists($id, $this->seasonCache)) {
            return $this->seasonCache[$id];
        }
        $seasons = $this->getSeasons();
        if (array_key_exists($id, $seasons)) {
            return $seasons[$id];
        }
        return null;
    }

    protected function convertSeason(stdClass $externalSeason): SeasonBase
    {
        $name = $this->getName( $externalSeason->name );
        if( array_key_exists( $name, $this->seasonCache ) ) {
            return $this->seasonCache[$name];
        }
        $season = new SeasonBase($name, $this->getPeriod($name));
        $season->setId($name);
        $this->seasonCache[$season->getId()] = $season;
        return $season;
    }

    protected function getName(string $name): string
    {
        $strposSlash = strpos($name, "/");
        if ( $strposSlash === false) {
            return $name;
        }
        $newName = substr($name, 0, $strposSlash ) . "/" . "20" . substr($name, $strposSlash + 1);
        if( $strposSlash === 2 ) {
            $newName = "20" . $newName;
        }
        return $newName;
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
