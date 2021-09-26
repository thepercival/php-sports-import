<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use stdClass;

class AgainstGameEvent
{
    public function __construct(
        public stdClass $player,
        public string $incidentClass,
        public string $incidentType,
        public int $time,
        public stdClass $playerOut,
        public stdClass $playerIn,
        public stdClass $assist1,
    ) {
    }


}