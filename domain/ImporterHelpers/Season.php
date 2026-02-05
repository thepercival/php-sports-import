<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use SportsImport\ExternalSource;
use Sports\Repositories\SeasonRepository;
use Sports\Season as SeasonBase;
use SportsImport\Attachers\SeasonAttacher as SeasonAttacher;
use Psr\Log\LoggerInterface;
use SportsImport\Repositories\AttacherRepository;

final class Season
{
    /** @var AttacherRepository<SeasonAttacher>  */
    protected AttacherRepository $seasonAttacherRepos;

    public function __construct(
        protected SeasonRepository $seasonRepos,
        protected EntityManagerInterface $entityManager,
        protected LoggerInterface $logger
    ) {
        $metadata = $entityManager->getClassMetadata(SeasonAttacher::class);
        $this->seasonAttacherRepos = new AttacherRepository($entityManager, $metadata);
    }

    /**
     * @param ExternalSource $externalSource
     * @param list<SeasonBase> $externalSourceSeasons
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceSeasons): void
    {
        foreach ($externalSourceSeasons as $externalSourceSeason) {
            $externalId = $externalSourceSeason->getId();
            if ($externalId === null) {
                continue;
            }
            $seasonAttacher = $this->seasonAttacherRepos->findOneByExternalId(
                $externalSource,
                (string)$externalId
            );
            if ($seasonAttacher === null) {
                $season = $this->createSeason($externalSourceSeason);
                $seasonAttacher = new SeasonAttacher(
                    $season,
                    $externalSource,
                    (string)$externalId
                );
                $this->entityManager->persist($seasonAttacher);
                $this->entityManager->flush();
            } else {
                $this->editSeason($seasonAttacher->getImportable(), $externalSourceSeason);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createSeason(SeasonBase $season): SeasonBase
    {
        $newSeason = new SeasonBase($season->getName(), $season->getPeriod());
        $this->entityManager->persist($newSeason);
        $this->entityManager->flush();
        return $newSeason;

    }

    protected function editSeason(SeasonBase $season, SeasonBase $externalSourceSeason): void
    {
        $season->setName($externalSourceSeason->getName());
        $this->entityManager->persist($season);
        $this->entityManager->flush();
    }
}
