<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelpers;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\CacheItemDbRepositoryInterface;
use SportsImport\ExternalSource\SofaScore;

final class PlayerApiHelper extends ApiHelperAbstract
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepositoryInterface $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    public function getImage(string $externalId): string
    {
        return $this->getImgData($this->getImageEndPoint($externalId));
    }

    #[\Override]
    public function getCacheMinutes(): int
    {
        // case ExternalSource::DATA_PERSON_IMAGE:
        return 60 * 24 * 365;
    }

    public function getDefaultEndPoint(): string
    {
        return "player/**personId**/image";
    }

    public function getImageEndPoint(string $externalId): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getImageEndPointSuffix($externalId);
    }

    protected function getImageEndPointSuffix(string $externalId): string
    {
        $slashPos = strpos($externalId, "/");
        if ($slashPos !== false) {
            $personImageId = substr($externalId, $slashPos + 1);
        } else {
            $personImageId = $externalId;
        }
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**personId**", $personImageId, $endpointSuffix);
    }
}
