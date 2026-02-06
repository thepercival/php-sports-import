<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper\Team;

use Sports\Competitor\StartLocationMap;
use Sports\Team\Role as TeamRole;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;

/**
 * @api
 * @template-extends SofaScoreHelper<TeamRole>
 */
final class Role extends SofaScoreHelper
{
//    protected StartLocationMap|null $placeLocationMap = null;


//    /**
//     * @param Game $game
//     * @return array|TeamRole[]
//     */
//    public function getTeamRoles( Game $game, TeamCompetitorAttacher $team, $externalTeamRoles ): array
//    {
//        $teamRoles = [];
//        $externalTeamRoles = $game->getTeamRoles();
//        foreach( $externalTeamRoles as $externalTeamRole ) {
//            $teamRole = $this->convertToTeamRole( $game, $externalTeamRole );
//            if( $teamRole === null ) {
//                continue;
//            }
//            $teamRoles[$teamRole->getId()] = $teamRole;
//        }
//        return $teamRoles;
//    }

//    /**
//     * @param string|int $id
//     * @return TeamRole|null
//     */
//    public function getTeamRole( $id ): ?TeamRole {
//        if( array_key_exists( $id, $this->teamRoleCache ) ) {
//            return $this->teamRoleCache[$id];
//        }
//        return null;
//    }


//    public function convertToTeamRole( Game $game, TeamCompetitorAttacher $team, stdClass $externalTeamRole ): ?TeamRole {
//        if( array_key_exists( $externalTeamRole->id, $this->teamRoleCache ) ) {
//            return $this->teamRoleCache[$externalTeamRole->id];
//        }
//
////        $teamRole = $this->getTeamRole($externalTeamRole->id);
////        if( $teamRole !== null ) {
////            return $teamRole;
////        }
////        $team = $this->parent->getTeam( $game->getPoule()->getRound()->getNumber()->getCompetition(), $externalTeamRole->id );
////        if( $team === null ) {
////            return null;
////        }
//        $person = $this->parent->convertToPerson( $externalTeamRole->person );
//        if( $person === null ) {
//            return null;
//        }
//        $line = 1;
//        $teamRole = new Player( $team, $person, $game->getPeriod(), $line );
//        $teamRole->setId( $externalTeamRole->slug );
//        $teamRole->setShirtNumber( $externalTeamRole->shirt );
//        $this->teamRoleCache[$teamRole->getId()] = $teamRole;
//        return $teamRole;
//    }
}
