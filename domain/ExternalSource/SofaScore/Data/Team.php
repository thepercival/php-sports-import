<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

/** @psalm-api */
final class Team
{
    public function __construct(
        public string $id,
        public string $shortName,
        public Team|null $parentTeam = null
    ) {}
}
