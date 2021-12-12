<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource;
use SportsImport\ExternalSource\CacheInfo;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Sport as SportData;
use stdClass;

class Sport extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @return list<SportData>
     */
    public function getSports(): array
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint(),
            $this->getCacheId(),
            $this->getCacheMinutes()
        );
        /** @var list<string> $sportNames */
        $sportNames = array_keys((array)$apiData);
        return array_map(function (string $sportName): SportData {
            return $this->convertApiDataRow($sportName);
        }, $sportNames);
    }

    protected function convertApiDataRow(string $sportName): SportData
    {
        return new SportData($sportName, $sportName);
    }

    public function getCacheMinutes(): int
    {
        return 60 * 24 * 30 * 6;
    }

    public function getEndPoint(): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getEndPointSuffix();
    }

    public function getCacheId(): string
    {
        return $this->getEndPointSuffix();
    }

    public function getDefaultEndPoint(): string
    {
        return "sport/7200/event-count";
    }

    public function getEndPointSuffix(): string
    {
        return $this->getDefaultEndPoint();
    }
}
