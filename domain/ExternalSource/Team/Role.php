<?php

namespace SportsImport\ExternalSource\Team;

use Sports\Team;
use Sports\Team\Role as TeamRole;
use Sports\Game;
use stdClass;

interface Role
{
//    /**
//     * @param Game $game
//     * @return array|TeamRole[]
//     */
//    public function getTeamRoles(Game $game): array;
//
//    /**
//     * @param string|int $id
//     * @return TeamRole|null
//     */
//    public function getTeamRole( $id ): ?TeamRole;

    public function convertToTeamRoleDEP( Game $game, Team $team, stdClass $externalTeamRole ): ?TeamRole;
}
