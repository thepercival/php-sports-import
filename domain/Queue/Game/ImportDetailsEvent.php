<?php

declare(strict_types=1);

namespace SportsImport\Queue\Game;

use Sports\Game;

interface ImportDetailsEvent
{
    public function sendUpdateGameDetailsEvent(Game $game): void;
}
