<?php

namespace SportsImport\ImporterHelpers;

use Exception;
use SportsImport\Attacher;
use Sports\Structure\Repository as StructureRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\ExternalSource;
use Sports\Structure as StructureBase;
use Sports\Competition;
use Sports\Structure\Copier as StructureCopier;
use Psr\Log\LoggerInterface;

class Structure
{
    public function __construct(
        private StructureCopier $structureCopier,
        private StructureRepository $structureRepos,
        private CompetitionAttacherRepository $competitionAttacherRepos,
        private LoggerInterface $logger
    ) {
        $this->structureCopier->setSportMappingPropertyToName();
    }

    public function import(ExternalSource $externalSource, StructureBase $externalSourceStructure): ?StructureBase
    {
        $externalCompetition = $externalSourceStructure->getFirstRoundNumber()->getCompetition();
        $competitionAttacher = $this->competitionAttacherRepos->findOneByExternalId(
            $externalSource,
            (string)$externalCompetition->getId()
        );
        if ($competitionAttacher === null) {
            return null;
        }
        $competition = $competitionAttacher->getImportable();

        $hasStructure = $this->structureRepos->hasStructure($competition);
        if( $hasStructure === false ) {
            $newStructure = $this->structureCopier->copy($externalSourceStructure, $competition);
            $this->structureRepos->add($newStructure, 1);
            $this->logger->info("structure added for external competition " . $externalCompetition->getName());
            return $newStructure;
        }

        // is needed for initialization
        $this->structureRepos->getStructure($competition);

        $newStructure = $this->structureCopier->copy($externalSourceStructure, $competition);

        $roundNumberAsValue = 1;
        $this->structureRepos->removeAndAdd($competition, $newStructure, $roundNumberAsValue);

        $this->logger->info("structure updated for external competition " . $externalCompetition->getName());
        return $newStructure;
    }
}
