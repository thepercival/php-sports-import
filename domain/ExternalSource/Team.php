<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Team as TeamBase;
use Sports\Competition;

interface Team
{
    /**
     * @param Competition $competition
     * @return list<TeamBase>
     */
    public function getTeams(Competition $competition): array;
    public function getTeam(Competition $competition, string|int $id): TeamBase|null;
    public function getImageTeam(string $teamExternalId): string;
}
