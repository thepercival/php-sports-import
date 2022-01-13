<?php

declare(strict_types=1);

namespace SportsImport\Queue\Game;

use Sports\Game;

interface ImportEvents
{
    public function sendCreateEvent(Game $newGame): void;

    public function sendRescheduleEvent(\DateTimeImmutable $oldStartDateTime, Game $updatedGame): void;

    public function sendUpdateBasicsEvent(Game $updatedGame): void;

    public function sendUpdateScoresLineupsAndEventsEvent(Game $updatedGame): void;
}
