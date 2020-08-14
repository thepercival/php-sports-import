<?php

namespace SportsImport\Service;

use Exception;
use SportsImport\ExternalSource;
use Sports\Season\Repository as SeasonRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use Sports\Season as SeasonBase;
use SportsImport\Attacher\Season as SeasonAttacher;
use Psr\Log\LoggerInterface;

class Season
{
    /**
     * @var SeasonRepository
     */
    protected $seasonRepos;
    /**
     * @var SeasonAttacherRepository
     */
    protected $seasonAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        SeasonRepository $seasonRepos,
        SeasonAttacherRepository $seasonAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->seasonRepos = $seasonRepos;
        $this->seasonAttacherRepos = $seasonAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|SeasonBase[] $externalSourceSeasons
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceSeasons)
    {
        foreach ($externalSourceSeasons as $externalSourceSeason) {
            $externalId = $externalSourceSeason->getId();
            $seasonAttacher = $this->seasonAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($seasonAttacher === null) {
                $season = $this->createSeason($externalSourceSeason);
                $seasonAttacher = new SeasonAttacher(
                    $season,
                    $externalSource,
                    $externalId
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
        $this->seasonRepos->save($newSeason);
        return $newSeason;
    }

    protected function editSeason(SeasonBase $season, SeasonBase $externalSourceSeason)
    {
        $season->setName($externalSourceSeason->getName());
        $this->seasonRepos->save($season);
    }
}
