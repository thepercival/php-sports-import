<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

/**
 * {"name":"England","slug":"england","priority":10,"id":1,"flag":"england"}
 */
class Association
{
    public function __construct(
        public string $id,
        public string $name
    ) {
    }
}
