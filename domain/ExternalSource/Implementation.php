<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

use SportsImport\ExternalSource;

interface Implementation
{
    public function getExternalSource(): ExternalSource;
}
