<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use SportsImport\ExternalSource\Sport as ExternalSourceSport;
use Sports\Sport as SportBase;
use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;
use Sports\Sport\Custom as SportCustom;
use stdClass;

class Sport extends SofaScoreHelper implements ExternalSourceSport
{
    /**
     * @var array|SportBase[]
     */
    protected $sportCache;
    /**
     * @var SportBase
     */
    protected $defaultSport;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->sportCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    public function getSports(): array
    {
        $externalSports = $this->apiHelper->getSportsData();
        $sports = [];
        foreach ($externalSports as $externalSportName => $value ) {
            $externalSport = new stdClass();
            $externalSport->name = $externalSportName;
            $sport = $this->convertToSport($externalSport) ;
            $sports[$sport->getId()] = $sport;
        }
        return $sports;
    }

    public function getSport($id = null): ?SportBase
    {
        if (array_key_exists($id, $this->sportCache)) {
            return $this->sportCache[$id];
        }
        $sports = $this->getSports();
        if (array_key_exists($id, $sports)) {
            return $sports[$id];
        }
        return null;
    }

    protected function convertToSport(stdClass $externalSport): SportBase
    {
        if( array_key_exists( $externalSport->name, $this->sportCache ) ) {
            return $this->sportCache[$externalSport->name];
        }
        $sport = new SportBase($externalSport->name);
        $sport->setTeam(false);
        $sport->setId($externalSport->name);
        $sport->setCustomId( $this->getCustomId($externalSport->name) );
        $this->sportCache[$sport->getId()] = $sport;
        return $sport;
    }

    protected function getCustomId( string $sportName ): ?int {
        if( $sportName === "football" ) {
            return SportCustom::Football;
        }
        return null;
    }
}
