<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 27-2-19
 * Time: 9:44
 */

//namespace Voetbal\Attacher\System\SofaScore;
//
//use Voetbal\Competition;
//use Voetbal\League;
//use Voetbal\Season;
//use Voetbal\Attacher\Season as SeasonAttacher;
//use Voetbal\Attacher\League as LeagueAttacher;
//
//trait Helper {
//
//    public function getExternalLeague( League $league ): ?LeagueAttacher {
//        $externalLeague = $this->externalLeagueRepos->findOneByImportable( $this->externalSystemBase, $league );
//        if( $externalLeague === null or strlen($externalLeague->getExternalId()) === 0 ) {
//            $this->addNotice('for league "'.$league->getName().'" there is no external object found' );
//            return null;
//        }
//        return $externalLeague;
//    }
//
//    public function getExternalSeason( Season $season ): ?SeasonAttacher {
//        $externalSeason = $this->externalSeasonRepos->findOneByImportable( $this->externalSystemBase, $season );
//        if( $externalSeason === null or strlen($externalSeason->getExternalId()) === 0 ) {
//            $this->addNotice('for season "'.$season->getName().'" there is no external object found' );
//            return null;
//        }
//        return $externalSeason;
//    }
//
//
//    public function getExternalsForCompetition( Competition $competition ): array {
//        return [
//            $this->getExternalLeague( $competition->getLeague() ),
//            $this->getExternalSeason( $competition->getSeason() )
//        ];
//    }
//
//}
