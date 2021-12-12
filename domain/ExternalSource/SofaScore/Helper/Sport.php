<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use SportsHelpers\GameMode;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Sport as SportApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\Data\Sport as SportData;
use Sports\Sport as SportBase;
use Sports\Sport\Custom as SportCustom;
use stdClass;

/**
 * @template-extends SofaScoreHelper<SportBase>
 */
class Sport extends SofaScoreHelper
{
    public function __construct(
        protected SportApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @return array<string|int, SportBase>
     */
    public function getSports(): array
    {
        $sportsData = $this->apiHelper->getSports();
        $sports = [];
        foreach ($sportsData as $sportData) {
            $sports[$sportData->id] = $this->convertDataToSport($sportData);
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

    protected function convertDataToSport(SportData $sportData): SportBase
    {
        if (array_key_exists($sportData->name, $this->cache)) {
            return $this->cache[$sportData->name];
        }
        $sport = new SportBase(
            $sportData->name,
            true,
            GameMode::Against,
            1
        );
        $sport->setId($sportData->name);
        $sport->setCustomId($this->getCustomId($sportData->name));
        $this->cache[$sportData->name] = $sport;
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
