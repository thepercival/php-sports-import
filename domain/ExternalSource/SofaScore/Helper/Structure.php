<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Sports\Competition;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use Sports\Structure as StructureBase;
use SportsImport\ExternalSource\Structure as ExternalSourceStructure;
use Sports\Structure\Service as StructureService;

class Structure extends SofaScoreHelper implements ExternalSourceStructure
{
    /**
     * @var StructureService
     */
    protected $structureService;
    /**
     * @var array|StructureBase[]
     */
    protected $structures = [];

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
        $this->structureService = new StructureService([]);
    }

    public function getStructure(Competition $competition): ?StructureBase
    {
        if( array_key_exists( $competition->getId(), $this->structures) ) {
            return $this->structures[$competition->getId()];
        }
        list($nrOfPlaces, $nrOfPoules) = $this->getPlacesAndPoules($competition);
        if ($nrOfPlaces === 0 || $nrOfPoules === 0) {
            return null;
        }
        $structure = $this->structureService->create($competition, $nrOfPlaces, $nrOfPoules);
        $this->structures[$competition->getId()] = $structure;
        return $structure;
    }

    protected function getPlacesAndPoules(Competition $competition): array
    {
        $apiData = $this->apiHelper->getStructureData($competition);
        return $this->getPlacesAndPoulesHelper($apiData);
    }

    protected function getPlacesAndPoulesHelper($apiData)
    {
        $nrOfPlaces = 0;
        $nrOfPoules = 0;
        if (property_exists($apiData, 'standingsTables')) {
            foreach ($apiData->standingsTables as $standingsTable) {
                $nrOfPoules++;
                $nrOfPlaces += count($standingsTable->tableRows);
            }
        }
        return [$nrOfPlaces,$nrOfPoules];
    }
}
