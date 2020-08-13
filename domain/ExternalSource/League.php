<?php

namespace Voetbal\ExternalSource;

use Voetbal\League as LeagueBase;

interface League
{
    /**
     * @return array|LeagueBase[]
     */
    public function getLeagues(): array;

    /**
     * @param mixed $id
     * @return LeagueBase|null
     */
    public function getLeague($id): ?LeagueBase;
}
