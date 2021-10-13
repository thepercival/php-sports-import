<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Competitor\Team as TeamCompetitor;
use Sports\Structure;
use Sports\Competition;
use Sports\Team as TeamBase;

interface CompetitionStructure
{
    public function getStructure(Competition $competition): Structure;
    /**
     * @return list<TeamCompetitor>
     */
    public function getTeamCompetitors(Competition $competition): array;
    public function getTeamCompetitor(Competition $competition, string|int $id): TeamCompetitor|null;
    /**
     * @param Competition $competition
     * @return list<TeamBase>
     */
    public function getTeams(Competition $competition): array;
    public function getTeam(Competition $competition, string|int $id): TeamBase|null;
    public function getImageTeam(string $teamExternalId): string;
}
