<?php

namespace SportsImport\ExternalSource\SofaScore\Helper\Team;

use DateTimeImmutable;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\League;
use Sports\Person as PersonBase;
use Sports\Sport;
use Sports\Team\Role as TeamRole;
use Sports\Team\Player;
use SportsImport\ExternalSource\NameAnalyzer;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Psr\Log\LoggerInterface;
use Sports\Place\Location\Map as PlaceLocationMap;
use SportsImport\ExternalSource\SofaScore;
use Sports\Competition;
use SportsImport\ExternalSource\Team\Role as ExternalSourceTeamRole;
use Sports\Poule;
use Sports\Place;
use Sports\Team;
use Sports\Game;

class Role extends SofaScoreHelper implements ExternalSourceTeamRole
{
    /**
     * @var array|TeamRole[]
     */
    protected $teamRoleCache;
    /**
     * @var PlaceLocationMap|null
     */
    protected $placeLocationMap;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->teamRoleCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

//    /**
//     * @param Game $game
//     * @return array|TeamRole[]
//     */
//    public function getTeamRoles( Game $game, Team $team, $externalTeamRoles ): array
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


    public function convertToTeamRole( Game $game, Team $team, stdClass $externalTeamRole ): ?TeamRole {
        if( array_key_exists( $externalTeamRole->id, $this->teamRoleCache ) ) {
            return $this->teamRoleCache[$externalTeamRole->id];
        }

//        $teamRole = $this->getTeamRole($externalTeamRole->id);
//        if( $teamRole !== null ) {
//            return $teamRole;
//        }
//        $team = $this->parent->getTeam( $game->getPoule()->getRound()->getNumber()->getCompetition(), $externalTeamRole->id );
//        if( $team === null ) {
//            return null;
//        }
        $person = $this->parent->convertToPerson( $externalTeamRole->person );
        if( $person === null ) {
            return null;
        }
        $line = 1;
        $teamRole = new Player( $team, $person, $game->getPeriod(), $line );
        $teamRole->setId( $externalTeamRole->slug );
        $teamRole->setShirtNumber( $externalTeamRole->shirt );
        $this->teamRoleCache[$teamRole->getId()] = $teamRole;
        return $teamRole;
    }
}
