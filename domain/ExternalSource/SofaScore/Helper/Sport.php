<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use SportsHelpers\GameMode;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use SportsImport\ExternalSource\Sport as ExternalSourceSport;
use Sports\Sport as SportBase;
use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;
use Sports\Sport\Custom as SportCustom;
use stdClass;

/**
 * @template-extends SofaScoreHelper<SportBase>
 */
class Sport extends SofaScoreHelper implements ExternalSourceSport
{
    /**
     * @return array<string|int, SportBase>
     */
    public function getSports(): array
    {
        $externalSports = $this->apiHelper->getSportsData();
        $sports = [];
        $externalSportNames = array_keys($externalSports);
        foreach ($externalSportNames as $externalSportName) {
            $externalSport = new stdClass();
            $externalSport->name = $externalSportName;
            $sport = $this->convertToSport($externalSport) ;
            $sports[$externalSportName] = $sport;
        }
        return $sports;
    }

    public function getSport(string|int $id): SportBase|null
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }
        $sports = $this->getSports();
        if (array_key_exists($id, $sports)) {
            return $sports[$id];
        }
        return null;
    }

    protected function convertToSport(stdClass $externalSport): SportBase
    {
        /** @var string $externalSportName */
        $externalSportName = $externalSport->name;
        if (array_key_exists($externalSportName, $this->cache)) {
            return $this->cache[$externalSportName];
        }
        $sport = new SportBase(
            $externalSportName,
            true,
            GameMode::AGAINST,
            1
        );
        $sport->setId($externalSportName);
        $sport->setCustomId($this->getCustomId($externalSportName));
        $this->cache[$externalSportName] = $sport;
        return $sport;
    }

    protected function getCustomId(string $sportName): int
    {
        if ($sportName === "football") {
            return SportCustom::Football;
        }
        return 0;
    }
}
