<?php

namespace Voetbal\ExternalSource;

use Voetbal\Structure as StructureBase;
use Voetbal\Competition;

interface Structure
{
    /**
     * @param Competition $competition
     * @return StructureBase
     */
    public function getStructure(Competition $competition): ?StructureBase;
}
