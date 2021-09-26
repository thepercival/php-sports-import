<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use stdClass;

class AgainstGameSidePlayers
{
    /**
     * @param list<Player> $players
     */
    public function __construct(
        public array $players
    ) {
    }
}
