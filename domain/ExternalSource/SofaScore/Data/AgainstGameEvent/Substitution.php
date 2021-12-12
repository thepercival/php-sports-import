<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent;

use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent as Base;
use SportsImport\ExternalSource\SofaScore\Data\Player;

class Substitution extends Base
{
    public function __construct(
        Player $player,
        int $time,
        public Player $substitute
    ) {
        parent::__construct($player, $time);
    }
}
