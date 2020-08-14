<?php

namespace SportsImport\ExternalSource;

use Sports\Structure as StructureBase;
use Sports\Competition;

interface Structure
{
    /**
     * @param Competition $competition
     * @return StructureBase
     */
    public function getStructure(Competition $competition): ?StructureBase;
}
