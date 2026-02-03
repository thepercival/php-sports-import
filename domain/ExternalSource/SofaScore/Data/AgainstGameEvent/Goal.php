<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent;

use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent as Base;
use SportsImport\ExternalSource\SofaScore\Data\Player;

final class Goal extends Base
{
    public function __construct(
        Player $player,
        int $time,
        public bool $penalty,
        public bool $own,
        public Player|null $assist
    ) {
        parent::__construct($player, $time);
    }
}
