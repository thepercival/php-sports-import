<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Exception;
use SportsImport\ExternalSource;
use Sports\Season\Repository as SeasonRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use Sports\Season as SeasonBase;
use SportsImport\Attacher\Season as SeasonAttacher;
use Psr\Log\LoggerInterface;

class Season
{
    public function __construct(
        protected SeasonRepository $seasonRepos,
        protected SeasonAttacherRepository $seasonAttacherRepos,
        protected LoggerInterface $logger
    ) {
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
                $this->seasonAttacherRepos->save($seasonAttacher);
            } else {
                $this->editSeason($seasonAttacher->getImportable(), $externalSourceSeason);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createSeason(SeasonBase $season): SeasonBase
    {
        $newSeason = new SeasonBase($season->getName(), $season->getPeriod());
        return $this->seasonRepos->save($newSeason);
    }

    protected function editSeason(SeasonBase $season, SeasonBase $externalSourceSeason): void
    {
        $season->setName($externalSourceSeason->getName());
        $this->seasonRepos->save($season);
    }
}
