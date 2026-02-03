<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use Sports\Sport;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Association as AssociationData;
use SportsImport\Repositories\CacheItemDbRepository as CacheItemDbRepository;
use stdClass;

final class Association extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param Sport $sport
     * @return list<AssociationData>
     */
    public function getAssociations(Sport $sport): array
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($sport),
            $this->getCacheId($sport),
            $this->getCacheMinutes()
        );
        if (property_exists($apiData, "categories") === false) {
            $this->logger->error('could not find stdClass-categories "round"');
            return [];
        }
        /** @var list<stdClass> $associationData */
        $associationData = $apiData->categories;
        return array_map(
            function (stdClass $apiDataRow): AssociationData {
                return $this->convertApiDataRow($apiDataRow);
            },
            $associationData
        );
    }


    protected function convertApiDataRow(stdClass $apiDataRow): AssociationData
    {
        return new AssociationData(
            (string)$apiDataRow->id,
            (string)$apiDataRow->name
        );
    }

    public function getCacheId(Sport $sport): string
    {
        return $this->getEndPointSuffix($sport);
    }

    #[\Override]
    public function getCacheMinutes(): int
    {
        return 60 * 24 * 30;
    }

    public function getDefaultEndPoint(): string
    {
        return "sport/**sportId**/categories";
    }

    public function getEndPoint(Sport $sport): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getEndPointSuffix($sport);
    }

    protected function getEndPointSuffix(Sport $sport): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**sportId**", $sport->getName(), $endpointSuffix);
    }
}
