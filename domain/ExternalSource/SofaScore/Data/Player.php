<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use stdClass;

class Player
{
    public function __construct(
        public stdClass $player,
        public array $statistics,
        public string $position
    ) {
    }
}