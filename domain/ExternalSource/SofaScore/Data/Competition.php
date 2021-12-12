<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

/**
 * {"name":"Premier League 20\/21","year":"20\/21","id":29415}
 */
class Competition
{
    public function __construct(
        public string $id,
        public string $name,
        public string $year
    ) {
    }
}
