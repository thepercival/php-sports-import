<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\Game\Statistics;

/** @api */
final readonly class Statistic
{
    public function __construct(
        public string $name,
        public int|float $value
    ) {}
}
