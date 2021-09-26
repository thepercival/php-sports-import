<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Sport as SportBase;

interface Sport
{
    /**
     * @return array<int|string, SportBase>
     */
    public function getSports(): array;
    public function getSport(string|int $id): SportBase|null;
}
