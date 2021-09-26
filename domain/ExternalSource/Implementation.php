<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use SportsImport\ExternalSource as ExternalSourceBase;

interface Implementation
{
    public function getExternalSource(): ExternalSourceBase;
}
