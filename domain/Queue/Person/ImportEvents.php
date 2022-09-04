<?php

declare(strict_types=1);

namespace SportsImport\Queue\Person;

use Sports\Person;
use Sports\Season;

interface ImportEvents
{
    public function sendCreatePersonEvent(Person $person, Season $season/*, \DateTimeImmutable $dateTime*/): void;

    // public function sendRescheduleEvent(\DateTimeImmutable $oldStartDateTime, Game $updatedGame): void;

    // public function sendUpdateBasicsEvent(Game $updatedGame): void;

    // public function sendUpdateScoresLineupsAndEventsEvent(Game $updatedGame): void;
}
