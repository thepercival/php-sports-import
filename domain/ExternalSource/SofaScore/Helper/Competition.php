<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Competition as CompetitionBase;
use SportsImport\ExternalSource;
use Psr\Log\LoggerInterface;
use SportsImport\Import\Service as ImportService;
use SportsImport\ExternalSource\SofaScore;
use Sports\Sport\Config\Service as SportConfigService;
use Sports\Sport;
use SportsImport\ExternalSource\Competition as ExternalSourceCompetition;

class Competition extends SofaScoreHelper implements ExternalSourceCompetition
{
    /**
     * @var array|CompetitionBase[]|null
     */
    protected $competitions;
    protected $sportConfigService;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->sportConfigService = new SportConfigService();
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    /**
     * @return array|CompetitionBase[]
     */
    public function getCompetitions(): array
    {
        $this->initCompetitions();
        return array_values($this->competitions);
    }

    public function getCompetition($id = null): ?CompetitionBase
    {
        $this->initCompetitions();
        if (array_key_exists((int)$id, $this->competitions)) {
            return $this->competitions[(int)$id];
        }
        return null;
    }

    protected function initCompetitions()
    {
        if ($this->competitions !== null) {
            return;
        }

        $sports = $this->parent->getSports();
        foreach ($sports as $sport) {
            if ($sport->getName() !== SofaScore::SPORTFILTER) {
                continue;
            }
            $apiData = $this->apiHelper->getCompetitionsData($sport);
            $this->setCompetitions($sport, $apiData->sportItem->tournaments);
        }
    }

    /**
     * {"name":"Premier Competition 19\/20","slug":"premier-competition-1920","year":"19\/20","id":23776}
     *
     * @param Sport $sport
     * @param array | stdClass[] $externalSourceCompetitions
     */
    protected function setCompetitions(Sport $sport, array $externalSourceCompetitions)
    {
        $this->competitions = [];
        /** @var stdClass $externalSourceCompetition */
        foreach ($externalSourceCompetitions as $externalSourceCompetition) {
            if ($externalSourceCompetition->tournament === null || !property_exists($externalSourceCompetition->tournament, "uniqueId")) {
                continue;
            }
            $league = $this->parent->getLeague($externalSourceCompetition->tournament->uniqueId);
            if ($league === null) {
                continue;
            }

            if ($externalSourceCompetition->season === null) {
                continue;
            }
            if (strlen($externalSourceCompetition->season->year) === 0) {
                continue;
            }
            $season = $this->parent->getSeason($externalSourceCompetition->season->year);
            if ($season === null) {
                continue;
            }

            $newCompetition = new CompetitionBase($league, $season);
            $newCompetition->setStartDateTime($season->getStartDateTime());
            $newCompetition->setId($externalSourceCompetition->season->id);
            $sportConfig = $this->sportConfigService->createDefault($sport, $newCompetition);
            $this->competitions[$newCompetition->getId()] = $newCompetition;
        }
    }
}
