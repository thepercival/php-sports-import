<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use DateTimeImmutable;

class Player
{
    public function __construct(
        public string $id,
        public string $name,
        public int $line,
        public DateTimeImmutable|null $dateOfBirth = null,
    ) {
    }
}
