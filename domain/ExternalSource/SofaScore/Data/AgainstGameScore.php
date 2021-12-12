<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class AgainstGameScore
{
    public function __construct(
        public int $current
    ) {
    }
}
