<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Sports\Competition\Sport as CompetitionSport;
use SportsHelpers\Sport\PersistVariant;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Competition as CompetitionBase;
use SportsImport\ExternalSource;
use Psr\Log\LoggerInterface;
use Sports\League;
use Sports\Season;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\Data\Competition as CompetitionData;
use Sports\Sport;
use SportsImport\ExternalSource\Competition as ExternalSourceCompetition;

/**
 * @template-extends SofaScoreHelper<CompetitionBase>
 */
class Competition extends SofaScoreHelper implements ExternalSourceCompetition
{
//    public function __construct(
//        SofaScore $parent,
//        SofaScoreApiHelper $apiHelper,
//        LoggerInterface $logger
//    ) {
//        $this->competitionCache = [];
//        parent::__construct(
//            $parent,
//            $apiHelper,
//            $logger
//        );
//    }

    /**
     * @param Sport $sport
     * @param League $league
     * @return array<int|string, CompetitionBase>
     */
    public function getCompetitions(Sport $sport, League $league): array
    {
        $competitions = [];
        $externalCompetitions = $this->apiHelper->getCompetitionsData($league);
        foreach ($externalCompetitions as $externalCompetition) {
            $competition = $this->convertToCompetition($sport, $league, $externalCompetition);
            if ($competition === null) {
                continue;
            }
            $competitionId = $competition->getId();
            if ($competitionId === null) {
                continue;
            }
            $competitions[$competitionId] = $competition;
        }
        return $competitions;
    }

    /**
     * @param Sport $sport,
     * @param League $league
     * @param Season $season
     * @return CompetitionBase|null
     */
    public function getCompetition(Sport $sport, League $league, Season $season): ?CompetitionBase
    {
        $competitions = $this->getCompetitions($sport, $league);
        foreach ($competitions as $competition) {
            if ($competition->getLeague() === $league && $competition->getSeason() === $season) {
                return $competition;
            }
        }
        return null;
    }

    protected function convertToCompetition(Sport $sport, League $league, CompetitionData $externalCompetition): ?CompetitionBase
    {
        if (array_key_exists($externalCompetition->id, $this->cache)) {
            return $this->cache[$externalCompetition->id];
        }
        $seasonId = $this->apiHelper->convertToSeasonId($externalCompetition->year);
        $season = $this->parent->getSeason($seasonId);
        if ($season === null) {
            return null;
        }
        $competition = new CompetitionBase($league, $season);
        $competition->setStartDateTime($season->getStartDateTime());
        $competition->setId($externalCompetition->id);
        new CompetitionSport(
            $sport,
            $competition,
            new PersistVariant(
                $sport->getDefaultGameMode(),
                $sport->getDefaultNrOfSidePlaces(),
                $sport->getDefaultNrOfSidePlaces(),
                0,
                2,
                0
            )
        );

        $this->cache[$externalCompetition->id] = $competition;
        return $competition;
    }
}
