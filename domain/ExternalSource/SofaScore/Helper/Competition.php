<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use Sports\Competition\Sport as CompetitionSport;
use Sports\Ranking\PointsCalculation;
use SportsHelpers\Sport\PersistVariant;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Competition as CompetitionApiHelper;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Sports\Competition as CompetitionBase;
use Sports\League;
use Sports\Season;
use SportsImport\ExternalSource\SofaScore\Data\Competition as CompetitionData;
use Sports\Sport;

/**
 * @template-extends SofaScoreHelper<CompetitionBase>
 */
class Competition extends SofaScoreHelper
{
    public function __construct(
        protected CompetitionApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @param Sport $sport
     * @param League $league
     * @return array<int|string, CompetitionBase>
     */
    public function getCompetitions(Sport $sport, League $league): array
    {
        $competitions = [];
        $competitionsData = $this->apiHelper->getCompetitions($league);
        foreach ($competitionsData as $competitionData) {
            $competition = $this->convertDataToCompetition($sport, $league, $competitionData);
            if ($competition === null) {
                continue;
            }
            $competitions[$competitionData->id] = $competition;
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

    protected function convertDataToCompetition(Sport $sport, League $league, CompetitionData $externalCompetition): ?CompetitionBase
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
            PointsCalculation::AgainstGamePoints,
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
