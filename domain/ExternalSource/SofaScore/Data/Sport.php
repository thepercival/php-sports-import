<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class Sport
{
    public function __construct(
        public string $id,
        public string $name
    ) {
    }
}