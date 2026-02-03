<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Transfer as TransferData;
use SportsImport\Repositories\CacheItemDbRepository as CacheItemDbRepository;
use stdClass;

final class Team extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param string $externalTeamId
     * @return list<TransferData>
     * @throws \Exception
     */
    public function getTransfers(string $externalTeamId): array
    {
        /** @var stdClass $json */
        $json = $this->getData(
            $this->getTransfersEndPoint($externalTeamId),
            $this->getTransfersCacheId($externalTeamId),
            $this->getCacheMinutes()
        );

        /** @var list<stdClass> $transfersInJson */
        $transfersInJson = $json->transfersIn;
        $transfersInData = array_map(function (stdClass $transferInJson): TransferData|null {
            return $this->jsonToDataConverter->convertTransferJsonToData($transferInJson);
        }, $transfersInJson);

        /** @var list<stdClass> $transfersOutJson */
        $transfersOutJson = $json->transfersOut;
        $transfersOutData = array_map(function (stdClass $transfersOutJson): TransferData|null {
            return $this->jsonToDataConverter->convertTransferJsonToData($transfersOutJson);
        }, $transfersOutJson);

        $transfersData = array_merge($transfersInData, $transfersOutData);

        $validTransfersData = array_filter($transfersData, function (TransferData|null $transfer): bool {
            return $transfer !== null;
        });
        return array_values($validTransfersData);
    }

    public function getTransfersDefaultEndPoint(): string
    {
        return "team/**teamId**/transfers";
    }

    public function getTransfersEndPoint(string $externalId): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getTransfersEndPointSuffix($externalId);
    }

    protected function getTransfersEndPointSuffix(string $externalId): string
    {
        $endpointSuffix = $this->getTransfersDefaultEndPoint();
        return str_replace("**teamId**", $externalId, $endpointSuffix);
    }

    public function getImage(string $externalId): string
    {
        return $this->getImgData($this->getImageEndPoint($externalId));
    }

    public function getImageDefaultEndPoint(): string
    {
        return "team/**teamId**/image";
    }

    public function getImageEndPoint(string $externalId): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getImageEndPointSuffix($externalId);
    }

    protected function getImageEndPointSuffix(string $externalId): string
    {
        $endpointSuffix = $this->getImageDefaultEndPoint();
        return str_replace("**teamId**", $externalId, $endpointSuffix);
    }

    public function getTransfersCacheId(string $externalTeamId): string
    {
        return $this->getTransfersEndPointSuffix($externalTeamId);
    }

    #[\Override]
    public function getCacheMinutes(): int
    {
        // case ExternalSource::DATA_TEAM_IMAGE:
        return 60 * 24;
    }
}
