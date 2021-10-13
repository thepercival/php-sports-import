<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Competitor\Team as TeamCompetitorApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\TeamCompetitor as TeamCompetitorData;
use Sports\Competition;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use Sports\Structure as StructureBase;
use Sports\Structure\Editor as StructureEditor;
use Sports\Competition\Sport\Service as CompetitionSportService;
use Sports\Planning\Config\Service as PlanningConfigService;
use stdClass;

/**
 * @template-extends SofaScoreHelper<StructureBase>
 */
class Structure extends SofaScoreHelper
{
    protected StructureEditor $structureEditor;

    public function __construct(
        protected TeamCompetitorApiHelper $teamCompetitorApiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent,$logger);
        $this->structureEditor = new StructureEditor(
            new CompetitionSportService(),
            new PlanningConfigService()
        );
    }

    public function getStructure(Competition $competition): StructureBase
    {
        $competitionId = $competition->getId();
        if ($competitionId === null) {
            throw new \Exception('no structure found for external competition "' . $competition->getName() . '"', E_ERROR);
        }
        if (array_key_exists($competitionId, $this->cache)) {
            return $this->cache[$competitionId];
        }
        list($nrOfPlaces, $nrOfPoules) = $this->getPlacesAndPoules($competition);
        if ($nrOfPlaces === 0 || $nrOfPoules === 0) {
            throw new \Exception('no places or place in structure for external competition "' . $competition->getName() . '"', E_ERROR);
        }
        $pouleStructure = $this->structureEditor->createBalanced($nrOfPlaces, $nrOfPoules);
        $structure = $this->structureEditor->create($competition, $pouleStructure->toArray());
        $this->cache[$competitionId] = $structure;
        return $structure;
    }

    /**
     * @param Competition $competition
     * @return list<int>
     */
    protected function getPlacesAndPoules(Competition $competition): array
    {
        $teamCompetitorsData = $this->teamCompetitorApiHelper->getTeamCompetitors($competition);
        return $this->getPlacesAndPoulesHelper($teamCompetitorsData);
    }

    /**
     * @param list<TeamCompetitorData> $teamCompetitorsData
     * @return list<int>
     */
    protected function getPlacesAndPoulesHelper(array $teamCompetitorsData): array
    {
        $nrOfPlaces = count($teamCompetitorsData);
        $nrOfPoules = 1;
//        if (!property_exists($apiData, 'standingsTables')) {
//            throw new \Exception('apiData has no property standingsTables', E_ERROR);
//        }
//        $standingsTables = $apiData->standingsTables;
//        if (!($standingsTables instanceof \Traversable)) {
//            throw new \Exception('apiData->standings is not traversable', E_ERROR);
//        }
//        /** @var stdClass $standingsTable */
//        foreach ($standingsTables as $standingsTable) {
//            $nrOfPoules++;
//            if (!property_exists($standingsTable, 'tableRows')) {
//                continue;
//            }
//            /** @var Countable $standingRow */
//            $standingRow = $standingsTable->tableRows;
//            $nrOfPlaces += count($standingRow);
//        }
        return [$nrOfPlaces,$nrOfPoules];
    }
}
