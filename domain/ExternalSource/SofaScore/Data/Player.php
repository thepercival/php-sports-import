<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use DateTimeImmutable;
use Sports\Sport\FootballLine;

final class Player
{
    public int $nrOfMinutesPlayed = 0;

    public function __construct(
        public string $id,
        public string $name,
        public FootballLine $line,
        public DateTimeImmutable|null $dateOfBirth = null,
        public int $marketValue = 0
    ) {
    }
}
