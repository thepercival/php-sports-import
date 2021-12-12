<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Player as PlayerApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Sports\Team as TeamBase;

/**
 * @template-extends SofaScoreHelper<TeamBase>
 */
class Player extends SofaScoreHelper
{
    public function __construct(
        protected PlayerApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    public function getImagePlayer(string $personExternalId): string
    {
        return $this->apiHelper->getImage($personExternalId);
    }
}
