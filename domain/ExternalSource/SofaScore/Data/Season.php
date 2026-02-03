<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

final class Season
{
    public function __construct(
        public string $name
    ) {
    }
}
