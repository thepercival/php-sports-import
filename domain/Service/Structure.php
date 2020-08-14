<?php

namespace SportsImport\Service;

use SportsImport\Attacher;
use Sports\Structure\Repository as StructureRepository;
use SportsImport\Attacher\Competitor\Repository as CompetitorAttacherRepository;
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

    public function import(ExternalSource $externalSource, StructureBase $externalSourceStructure )
    {
        /** @var Attacher|null $competitionAttacher */
        $competitionAttacher = $this->competitionAttacherRepos->findOneByExternalId(
            $externalSource,
            $externalSourceStructure->getFirstRoundNumber()->getCompetition()->getId()
        );
        if ($competitionAttacher === null) {
            return;
        }
        /** @var Competition $competition */
        $competition = $competitionAttacher->getImportable();

        $structure = $this->structureRepos->getStructure($competition);
        if ($structure !== null) {
            return;
        }

        // @TODO DEPRECATED
//        $externalSourceCompetitors = $externalSourceStructure->getFirstRoundNumber()->getCompetitors();
//
//        $existingCompetitors = $this->getCompetitors($externalSource, $externalSourceCompetitors);
//
//        $structureCopier = new StructureCopier($competition, $existingCompetitors);
//        $newStructure = $structureCopier->copy($externalSourceStructure);
//
//        $roundNumberAsValue = 1;
//        $this->structureRepos->removeAndAdd($competition, $newStructure, $roundNumberAsValue);
    }

    protected function getCompetitors(ExternalSource $externalSource, array $externalSourceCompetitors): array
    {
        // @TODO DEPRECATED
        $competitors = [];
//        foreach ($externalSourceCompetitors as $externalSourceCompetitor) {
//            $competitorAttacher = $this->competitorAttacherRepos->findOneByExternalId(
//                $externalSource,
//                $externalSourceCompetitor->getId()
//            );
//            if ($competitorAttacher === null) {
//                continue;
//            }
//            $competitors[] = $competitorAttacher->getImportable();
//        }
        return $competitors;
    }
}
