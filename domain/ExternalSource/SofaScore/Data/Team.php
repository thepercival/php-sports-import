<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class Team
{
    public function __construct(
        public string $id,
        public string $shortName
    ) {
    }
}