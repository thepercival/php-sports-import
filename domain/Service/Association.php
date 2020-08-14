<?php

namespace SportsImport\Service;

use SportsImport\ExternalSource;
use Sports\Association\Repository as AssociationRepository;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use Sports\Association as AssociationBase;
use SportsImport\Attacher\Association as AssociationAttacher;

use Psr\Log\LoggerInterface;

class Association
{
    /**
     * @var AssociationRepository
     */
    protected $associationRepos;
    /**
     * @var AssociationAttacherRepository
     */
    protected $associationAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        AssociationRepository $associationRepos,
        AssociationAttacherRepository $associationAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->associationRepos = $associationRepos;
        $this->associationAttacherRepos = $associationAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|AssociationBase[] $externalSourceAssociations
     * @throws \Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceAssociations)
    {
        foreach ($externalSourceAssociations as $externalSourceAssociation) {
            $externalId = $externalSourceAssociation->getId();
            $associationAttacher = $this->associationAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($associationAttacher === null) {
                $association = $this->createAssociation($externalSource, $externalSourceAssociation);
                $associationAttacher = new AssociationAttacher(
                    $association,
                    $externalSource,
                    $externalId
                );
                $this->associationAttacherRepos->save($associationAttacher);
            } else {
                $this->editAssociation($associationAttacher->getImportable(), $externalSourceAssociation);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createAssociation(ExternalSource $externalSource, AssociationBase $association): AssociationBase
    {
        $newAssociation = new AssociationBase($association->getName());
        $parentAssociation = null;
        if ($association->getParent() !== null) {
            $parentAssociation = $this->associationAttacherRepos->findImportable(
                $externalSource,
                $association->getParent()->getId()
            );
        }
        $newAssociation->setParent($parentAssociation);
        $this->associationRepos->save($newAssociation);
        return $newAssociation;
    }

    protected function editAssociation(AssociationBase $association, AssociationBase $externalSourceAssociation)
    {
        $association->setName($externalSourceAssociation->getName());
        $this->associationRepos->save($association);
    }
}
