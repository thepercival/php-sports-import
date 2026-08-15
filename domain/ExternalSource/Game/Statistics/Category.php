<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\Game\Statistics;

/** @api */
final readonly class Category
{
    /**
     * @param list<Statistic> $statistics
     */
    public function __construct(
        public string $name,
        public array $statistics
    ) {}
}
