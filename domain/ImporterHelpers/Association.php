<?php

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use SportsImport\ExternalSource;
use Sports\Association as AssociationBase;
use SportsImport\Attachers\AssociationAttacher as AssociationAttacher;

use Psr\Log\LoggerInterface;
use SportsImport\Repositories\AttacherRepository;

final class Association
{
    /** @var EntityRepository<AssociationBase>  */
    protected EntityRepository $associationRepos;
    /** @var AttacherRepository<AssociationAttacher>  */
    protected AttacherRepository $associationAttacherRepos;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected LoggerInterface $logger
    ) {
        $metadata = $entityManager->getClassMetadata(AssociationBase::class);
        $this->associationRepos = new EntityRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(AssociationAttacher::class);
        $this->associationAttacherRepos = new AttacherRepository($entityManager, $metadata);
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
                $this->entityManager->persist($associationAttacher);
                $this->entityManager->flush();
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
                $attacher = $this->associationAttacherRepos->findOneByExternalId($externalSource, (string)$parentExternalId);
                $parentAssociation = $attacher?->getImportable();
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
