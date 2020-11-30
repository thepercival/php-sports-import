<?php

namespace SportsImport\Queue\Game;

use Sports\Game;

interface ImportDetailsEvent
{
    public function sendUpdateGameDetailsEvent(Game $game);
}
