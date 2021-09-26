<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Countable;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Competition;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use Sports\Structure as StructureBase;
use SportsImport\ExternalSource\Structure as ExternalSourceStructure;
use Sports\Structure\Editor as StructureEditor;
use Sports\Competition\Sport\Service as CompetitionSportService;
use Sports\Planning\Config\Service as PlanningConfigService;
use stdClass;

/**
 * @template-extends SofaScoreHelper<StructureBase>
 */
class Structure extends SofaScoreHelper implements ExternalSourceStructure
{
    protected StructureEditor $structureEditor;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
        $this->structureEditor = new StructureEditor(
            new CompetitionSportService(),
            new PlanningConfigService()
        );
    }

    public function getStructure(Competition $competition): ?StructureBase
    {
        $competitionId = $competition->getId();
        if ($competitionId === null) {
            return null;
        }
        if (array_key_exists($competitionId, $this->cache)) {
            return $this->cache[$competitionId];
        }
        list($nrOfPlaces, $nrOfPoules) = $this->getPlacesAndPoules($competition);
        if ($nrOfPlaces === 0 || $nrOfPoules === 0) {
            return null;
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
        $apiData = $this->apiHelper->getStructureData($competition);
        return $this->getPlacesAndPoulesHelper($apiData);
    }

    /**
     * @param stdClass $apiData
     * @return list<int>
     */
    protected function getPlacesAndPoulesHelper(stdClass $apiData): array
    {
        $nrOfPlaces = 0;
        $nrOfPoules = 0;
        if (!property_exists($apiData, 'standingsTables')) {
            throw new \Exception('apiData has no property standingsTables', E_ERROR);
        }
        $standingsTables = $apiData->standingsTables;
        if (!($standingsTables instanceof \Traversable)) {
            throw new \Exception('apiData->standings is not traversable', E_ERROR);
        }
        /** @var stdClass $standingsTable */
        foreach ($standingsTables as $standingsTable) {
            $nrOfPoules++;
            if (!property_exists($standingsTable, 'tableRows')) {
                continue;
            }
            /** @var Countable $standingRow */
            $standingRow = $standingsTable->tableRows;
            $nrOfPlaces += count($standingRow);
        }
        return [$nrOfPlaces,$nrOfPoules];
    }
}
