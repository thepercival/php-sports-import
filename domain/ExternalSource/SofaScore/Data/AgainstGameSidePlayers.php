<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class AgainstGameSidePlayers
{
    /**
     * @param list<Player> $players
     */
    public function __construct(
        public int $againstSide,
        public array $players
    ) {
    }
}
