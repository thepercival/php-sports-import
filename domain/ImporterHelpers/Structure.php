<?php

namespace SportsImport\ImporterHelpers;

use Sports\Competition\Sport\FromToMapper as CompetitionSportFromToMapper;
use Sports\Competition\Sport\FromToMapStrategy;
use Sports\Poule\Horizontal\Creator as HorizontalPouleCreator;
use Sports\Qualify\Rule\Creator as QualifyRuleCreator;
use Sports\Structure\Repository as StructureRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\ExternalSource;
use Sports\Structure as StructureBase;
use Sports\Structure\Copier as StructureCopier;
use Psr\Log\LoggerInterface;

class Structure
{
    public function __construct(
        private StructureRepository $structureRepos,
        private CompetitionAttacherRepository $competitionAttacherRepos,
        private LoggerInterface $logger
    ) {
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
        $toCompetition = $competitionAttacher->getImportable();

        $structureCopier = new StructureCopier(
            new HorizontalPouleCreator(),
            new QualifyRuleCreator(),
            new CompetitionSportFromToMapper (
                array_values($externalSourceStructure->getFirstRoundNumber()->getCompetition()->getSports()->toArray()),
                array_values($toCompetition->getSports()->toArray()),
                FromToMapStrategy::ByProperties
            )
        );

        $hasStructure = $this->structureRepos->hasStructure($toCompetition);
        if ($hasStructure === false) {
            $newStructure = $structureCopier->copy($externalSourceStructure, $toCompetition);
            $this->structureRepos->add($newStructure);
            $this->logger->info("structure added for external competition " . $externalCompetition->getName());
            return $newStructure;
        }

        // is needed for initialization
        $this->structureRepos->getStructure($toCompetition);

        $newStructure = $structureCopier->copy($externalSourceStructure, $toCompetition);

        $this->structureRepos->remove($toCompetition);
        $this->structureRepos->add($newStructure);

        $this->logger->info("structure updated for external competition " . $externalCompetition->getName());
        return $newStructure;
    }
}
