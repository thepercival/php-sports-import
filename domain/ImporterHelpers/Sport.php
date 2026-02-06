<?php

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use SportsImport\ExternalSource;
use Sports\Repositories\SportRepository;
use Sports\Sport as SportBase;
use SportsImport\Attachers\SportAttacher;
use SportsImport\Repositories\AttacherRepository;

/**
 * @api
 */
final class Sport
{
    /** @var AttacherRepository<SportAttacher> */
    protected AttacherRepository $sportAttacherRepos;

    public function __construct(
        protected SportRepository $sportRepos,
        protected EntityManagerInterface $entityManager
    ) {
        $metadata = $entityManager->getClassMetadata(SportAttacher::class);
        $this->sportAttacherRepos = new AttacherRepository($entityManager, $metadata);
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<SportBase> $externalSourceSports
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceSports): void
    {
        foreach ($externalSourceSports as $externalSourceSport) {
            $externalId = $externalSourceSport->getId();
            if ($externalId === null) {
                continue;
            }
            $sportAttacher = $this->sportAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
            );
            if ($sportAttacher === null) {
                $sport = $this->createSport($externalSourceSport);
                $sportAttacher = new SportAttacher(
                    $sport,
                    $externalSource,
                    (string)$externalId
                );
                $this->entityManager->persist($sportAttacher);
                $this->entityManager->flush();
            } else {
                $this->editSport($sportAttacher->getImportable(), $externalSourceSport);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createSport(SportBase $sport): SportBase
    {
        $existingSport = $this->sportRepos->findOneBy(["name" => $sport->getName()]);
        if ($existingSport !== null) {
            return $existingSport;
        }
        $newSport = new SportBase(
            $sport->getName(),
            $sport->getTeam(),
            $sport->getDefaultGameMode(),
            $sport->getDefaultNrOfSidePlaces()
        );
        $this->entityManager->persist($newSport);
        $this->entityManager->flush();
        return $newSport;
    }

    protected function editSport(SportBase $sport, SportBase $externalSourceSport): void
    {
        $sport->setName($externalSourceSport->getName());
        $sport->setCustomId($externalSourceSport->getCustomId());
        $this->entityManager->persist($sport);
        $this->entityManager->flush();
    }
}
