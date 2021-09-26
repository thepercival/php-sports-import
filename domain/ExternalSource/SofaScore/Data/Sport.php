<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class Sport
{
    public function __construct(
        public int|string $id,
        public string $name
    ) {
    }
}