<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent;

use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent as Base;
use SportsImport\ExternalSource\SofaScore\Data\Player;

class Card extends Base
{
    public function __construct(Player $player, int $time, public int $color)
    {
        parent::__construct($player, $time);
    }
}
