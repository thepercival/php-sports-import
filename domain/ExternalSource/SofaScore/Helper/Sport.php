<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use SportsImport\ExternalSource\Sport as ExternalSourceSport;
use Sports\Sport as SportBase;
use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;
use SportsImport\Import\Service as ImportService;
use stdClass;

class Sport extends SofaScoreHelper implements ExternalSourceSport
{
    /**
     * @var array|SportBase[]|null
     */
    protected $sports;
    /**
     * @var SportBase
     */
    protected $defaultSport;

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

    public function getSports(): array
    {
        $this->initSports();
        return array_values($this->sports);
    }

    public function getSport($id = null): ?SportBase
    {
        $sports = $this->getSports();
        if (array_key_exists($id, $sports)) {
            return $sports[$id];
        }
        return null;
    }

    protected function initSports()
    {
        if ($this->sports !== null) {
            return;
        }
        $this->setSports($this->getSportData());
    }

    /**
     * @return array|stdClass[]
     */
    protected function getSportData(): array
    {
        // $apiData = $this->apiHelper->getSportsData();
        // return get_object_vars($apiData);
        return ["football" => new stdClass() ];
    }

    protected function setSports(array $externalSourceSports)
    {
        $this->sports = [];
        foreach ($externalSourceSports as $sportName => $value) {
            if ($this->hasName($this->sports, $sportName)) {
                continue;
            }
            $sport = $this->createSport($sportName) ;
            $this->sports[$sport->getId()] = $sport;
        }
    }

    protected function createSport(string $name): SportBase
    {
        $sport = new SportBase($name);
        $sport->setTeam(false);
        $sport->setId($name);
        return $sport;
    }
}
