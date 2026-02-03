<?php

namespace SportsImport\ImporterHelpers;

use Exception;
use SportsImport\ExternalSource;
use Sports\Association\Repository as AssociationRepository;
use SportsImport\Attachers\Association\AttacherRepository as AssociationAttacherRepository;
use Sports\Association as AssociationBase;
use SportsImport\Attachers\AssociationAttacher as AssociationAttacher;

use Psr\Log\LoggerInterface;

final class Association
{
    public function __construct(
        protected AssociationRepository $associationRepos,
        protected AssociationAttacherRepository $associationAttacherRepos,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<AssociationBase> $externalSourceAssociations
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceAssociations): void
    {
        foreach ($externalSourceAssociations as $externalSourceAssociation) {
            $externalId = $externalSourceAssociation->getId();
            if ($externalId === null) {
                continue;
            }
            $associationAttacher = $this->associationAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
            );
            if ($associationAttacher === null) {
                $association = $this->createAssociation($externalSource, $externalSourceAssociation);
                $associationAttacher = new AssociationAttacher(
                    $association,
                    $externalSource,
                    (string)$externalId
                );
                $this->associationAttacherRepos->save($associationAttacher);
            } else {
                $this->editAssociation($associationAttacher->getImportable(), $externalSourceAssociation);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createAssociation(ExternalSource $externalSource, AssociationBase $externalAssociation): AssociationBase
    {
        $newAssociation = new AssociationBase($externalAssociation->getName());
        $parentAssociation = null;
        $externalParentAssociation = $externalAssociation->getParent();
        if ($externalParentAssociation !== null) {
            $parentExternalId = $externalParentAssociation->getId();
            if ($parentExternalId !== null) {
                $parentAssociation = $this->associationAttacherRepos->findImportable(
                    $externalSource,
                    (string)$parentExternalId
                );
            }
        }
        $newAssociation->setParent($parentAssociation);
        $this->associationRepos->save($newAssociation);
        return $newAssociation;
    }

    protected function editAssociation(AssociationBase $association, AssociationBase $externalSourceAssociation): void
    {
        $association->setName($externalSourceAssociation->getName());
        $this->associationRepos->save($association);
    }
}
