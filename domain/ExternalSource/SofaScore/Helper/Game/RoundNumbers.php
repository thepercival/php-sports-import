<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper\Game;

use Psr\Log\LoggerInterface;
use Sports\Competition;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\GameRoundNumbers as GameRoundNumbersApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;

/**
 * @template-extends SofaScoreHelper<list<int>>
 */
final class RoundNumbers extends SofaScoreHelper
{
    public function __construct(
        protected GameRoundNumbersApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @return list<int>
     */
    public function getGameRoundNumbers(Competition $competition): array
    {
        $competitionId = (string)$competition->getId();
        if (array_key_exists($competitionId, $this->cache)) {
            return $this->cache[$competitionId];
        }
        $gameRoundNumbers = $this->apiHelper->getGameRoundNumbers($competition);

        $this->cache[$competitionId] = $gameRoundNumbers;
        return $gameRoundNumbers;
    }
}
