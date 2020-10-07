<?php

namespace SportsImport\ExternalSource;

use Sports\Sport as SportBase;

interface Sport
{
    /**
     * @return array|SportBase[]
     */
    public function getSports(): array;
    /**
     * @param string|int $id
     * @return SportBase|null
     */
    public function getSport($id): ?SportBase;
}
