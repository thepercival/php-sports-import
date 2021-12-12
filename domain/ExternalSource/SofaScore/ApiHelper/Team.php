<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Team as TeamData;
use stdClass;

class Team extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    public function getImage(string $externalId): string
    {
        return $this->getImgData($this->getImageEndPoint($externalId));
    }

    public function getCacheMinutes(): int
    {
        // case ExternalSource::DATA_TEAM_IMAGE:
        return 60 * 24;
    }

    public function getDefaultEndPoint(): string
    {
        return "images/team-logo/football_**teamId**.png";
    }

    public function getImageEndPoint(string $externalId): string
    {
        return self::IMAGEBASEURL . $this->getImageEndPointSuffix($externalId);
        // return $this->sofaScore->getExternalSource()->getApiurl() . $this->getImageEndPointSuffix($externalId);
    }

    protected function getImageEndPointSuffix(string $externalId): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**teamId**", $externalId, $endpointSuffix);
    }

    public function convertApiDataRow(stdClass $apiDataRow): TeamData|null
    {
        if (!property_exists($apiDataRow, "id")) {
            $this->logger->error('could not find stdClass-property "id"');
            return null;
        }
        if (!property_exists($apiDataRow, 'shortName')) {
            $this->logger->error('could not find stdClass-property "shortName"');
            return null;
        }
        return new TeamData(
            (string)$apiDataRow->id,
            (string)$apiDataRow->shortName
        );
    }
}
