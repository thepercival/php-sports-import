<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class Status
{
    public function __construct(
        public int $code
    ) {
    }
}