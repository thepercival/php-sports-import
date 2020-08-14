<?php

namespace SportsImport\ExternalSource;

use SportsImport\ExternalSource as ExternalSourceBase;

interface Implementation
{
    public function getExternalSource();
    public function setExternalSource(ExternalSourceBase $externalSource);
}
