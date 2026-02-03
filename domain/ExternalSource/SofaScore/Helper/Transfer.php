<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use Sports\Team as TeamBase;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Team as TeamApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Transfer as TransferData;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Person as PersonHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Team as TeamHelper;
use SportsImport\Transfer as TransferBase;

/**
 * @template-extends SofaScoreHelper<TransferBase>
 */
final class Transfer extends SofaScoreHelper
{
    public function __construct(
        protected PersonHelper $personHelper,
        protected TeamHelper $teamHelper,
        protected TeamApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }


    /**
     * @param TeamBase $externalTeam
     * @return list<TransferBase>
     */
    public function getTransfers(TeamBase $externalTeam): array
    {
        $transfersData = $this->apiHelper->getTransfers((string)$externalTeam->getId());
        return array_map(function (TransferData $transferData) use ($externalTeam): TransferBase {
            return new TransferBase(
                $this->personHelper->convertDataToPerson($transferData->player),
                $transferData->dateTime,
                $this->teamHelper->convertDataToTeam($externalTeam->getAssociation(), $transferData->transferFrom),
                $this->teamHelper->convertDataToTeam($externalTeam->getAssociation(), $transferData->transferTo),
                $transferData->player->line
            );
        }, $transfersData);
    }

}
