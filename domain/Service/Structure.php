<?php

namespace SportsImport\Service;

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
    /**
     * @var StructureRepository
     */
    protected $structureRepos;
    /**
     * @var CompetitionAttacherRepository
     */
    protected $competitionAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        StructureRepository $structureRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->structureRepos = $structureRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
    }

    public function import(ExternalSource $externalSource, StructureBase $externalSourceStructure ): ?StructureBase
    {
        $externalCompetition = $externalSourceStructure->getFirstRoundNumber()->getCompetition();
        /** @var Attacher|null $competitionAttacher */
        $competitionAttacher = $this->competitionAttacherRepos->findOneByExternalId(
            $externalSource,
            $externalCompetition->getId()
        );
        if ($competitionAttacher === null) {
            return null;
        }
        /** @var Competition $competition */
        $competition = $competitionAttacher->getImportable();

        $structure = $this->structureRepos->getStructure($competition);
        if ($structure !== null) {
            return null;
        }

        $structureCopier = new StructureCopier($competition);
        $newStructure = $structureCopier->copy($externalSourceStructure);

        $roundNumberAsValue = 1;
        $this->structureRepos->removeAndAdd($competition, $newStructure, $roundNumberAsValue);

        $this->logger->info("structure added for external competition " . $externalCompetition->getName() );
        return $newStructure;
    }
}
