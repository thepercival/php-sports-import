<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Competition as CompetitionBase;
use SportsImport\ExternalSource;
use Psr\Log\LoggerInterface;
use Sports\League;
use Sports\Season;
use SportsImport\ExternalSource\SofaScore;
use Sports\Sport\Config\Service as SportConfigService;
use Sports\Sport;
use SportsImport\ExternalSource\Competition as ExternalSourceCompetition;

class Competition extends SofaScoreHelper implements ExternalSourceCompetition
{
    /**
     * @var array|CompetitionBase[]
     */
    protected $competitionCache;
    protected $sportConfigService;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->sportConfigService = new SportConfigService();
        $this->competitionCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    /**
     * @param Sport $sport
     * @param League $league
     * @return array|CompetitionBase[]
     */
    public function getCompetitions( Sport $sport, League $league ): array
    {
        $competitions = [];
        $externalCompetitions = $this->apiHelper->getCompetitionsData($league);
        foreach( $externalCompetitions as $externalCompetition ) {
            $competition = $this->convertToCompetition( $sport, $league, $externalCompetition );
            if( $competition === null ) {
                continue;
            }
            $competitions[$competition->getId()] = $competition;
        }
        return $competitions;
    }

    /**
     * @param Sport $sport,
     * @param League $league
     * @param Season $season
     * @return CompetitionBase|null
     */
    public function getCompetition( Sport $sport, League $league, Season $season): ?CompetitionBase {
        $competitions = $this->getCompetitions( $sport, $league);
        foreach( $competitions as $competition ) {
            if( $competition->getLeague() === $league && $competition->getSeason() === $season) {
                return $competition;
            }
        }
        return null;
    }

    /**
     *  {"name":"Premier League 20\/21","year":"20\/21","id":29415}
     *
     * @param Sport $sport
     * @param League $league
     * @param stdClass $externalCompetition
     * @return CompetitionBase|null
     */
    protected function convertToCompetition(Sport $sport, League $league, stdClass $externalCompetition): ?CompetitionBase
    {
        if( array_key_exists( $externalCompetition->id, $this->competitionCache ) ) {
            return $this->competitionCache[$externalCompetition->id];
        }
        $seasonId = $this->apiHelper->convertToSeasonId( $externalCompetition->year );
        $season = $this->parent->getSeason( $seasonId );
        if( $season === null ) {
            return null;
        }
        $competition = new CompetitionBase($league, $season);
        $competition->setStartDateTime($season->getStartDateTime());
        $competition->setId($externalCompetition->id);
        $this->sportConfigService->createDefault($sport, $competition);
        $this->competitionCache[$competition->getId()] = $competition;
        return $competition;

    }
}
