<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

/** @api */
final readonly class GameParticipationStatistic
{
    public function __construct(
        public string $name,
        public int|float $value
    ) {}
}
