<?php

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use SportsImport\Attachers\AssociationAttacher;
use SportsImport\ExternalSource;
use Sports\League as LeagueBase;
use SportsImport\Attachers\LeagueAttacher as LeagueAttacher;
use SportsImport\Repositories\AttacherRepository;

/**
 * @api
 */
final class League
{
    /** @var EntityRepository<League>  */
    protected EntityRepository $leagueRepos;
    /** @var AttacherRepository<LeagueAttacher>  */
    protected AttacherRepository $leagueAttacherRepos;
    /** @var AttacherRepository<AssociationAttacher>  */
    protected AttacherRepository $associationAttacherRepos;

    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {
        $metaData = $entityManager->getClassMetadata(League::class);
        $this->leagueRepos = new EntityRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(LeagueAttacher::class);
        $this->leagueAttacherRepos = new AttacherRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(AssociationAttacher::class);
        $this->associationAttacherRepos = new AttacherRepository($entityManager, $metaData);
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<LeagueBase> $externalSourceLeagues
     * @throws \Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceLeagues): void
    {
        foreach ($externalSourceLeagues as $externalSourceLeague) {
            $externalId = $externalSourceLeague->getId();
            if ($externalId === null) {
                continue;
            }
            $leagueAttacher = $this->leagueAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
            );
            if ($leagueAttacher === null) {
                $league = $this->createLeague($externalSource, $externalSourceLeague);
                if ($league === null) {
                    continue;
                }
                $leagueAttacher = new LeagueAttacher(
                    $league,
                    $externalSource,
                    (string)$externalId
                );
                $this->entityManager->persist($leagueAttacher);
                $this->entityManager->flush();
            } else {
                $this->editLeague($leagueAttacher->getImportable(), $externalSourceLeague);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createLeague(ExternalSource $externalSource, LeagueBase $externalSourceLeague): ?LeagueBase
    {
        $attacher = $this->associationAttacherRepos->findOneByExternalId($externalSource, (string)$externalSourceLeague->getAssociation()->getId());
        $association = $attacher?->getImportable();

        if ($association === null) {
            return null;
        }
        $league = new LeagueBase($association, $externalSourceLeague->getName());
        $this->leagueRepos->save($league);
        return $league;
    }

    protected function editLeague(LeagueBase $league, LeagueBase $externalSourceLeague): void
    {
        $league->setName($externalSourceLeague->getName());
        $this->leagueRepos->save($league);
    }
}
