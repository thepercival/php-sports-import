<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use SportsHelpers\Against\Side as AgainstSide;

final class AgainstGameSidePlayers
{
    /**
     * @param list<Player> $players
     */
    public function __construct(
        public AgainstSide $againstSide,
        public array $players
    ) {
    }
}
