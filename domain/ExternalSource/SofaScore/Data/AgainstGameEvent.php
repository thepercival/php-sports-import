<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class AgainstGameEvent
{
    public function __construct(public Player $player, public int $time)
    {
    }
}
