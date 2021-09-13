<?php
declare(strict_types=1);

namespace SportsImport\Queue\Game;

use Sports\Game;

interface ImportEvent
{
    public function sendUpdateGameEvent(Game $game, \DateTimeImmutable $oldStartDateTime = null): void;
}
