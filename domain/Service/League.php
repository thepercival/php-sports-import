<?php

namespace SportsImport\Service;

use SportsImport\ImporterInterface;
use SportsImport\ExternalSource;
use Sports\League\Repository as LeagueRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use Sports\League as LeagueBase;
use SportsImport\Attacher\League as LeagueAttacher;
use Psr\Log\LoggerInterface;

class League implements ImporterInterface
{
    /**
     * @var LeagueRepository
     */
    protected $leagueRepos;
    /**
     * @var LeagueAttacherRepository
     */
    protected $leagueAttacherRepos;
    /**
     * @var AssociationAttacherRepository
     */
    protected $associationAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        LeagueRepository $leagueRepos,
        LeagueAttacherRepository $leagueAttacherRepos,
        AssociationAttacherRepository $associationAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->leagueRepos = $leagueRepos;
        $this->leagueAttacherRepos = $leagueAttacherRepos;
        $this->associationAttacherRepos = $associationAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array $externalSourceLeagues
     * @throws \Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceLeagues)
    {
        /** @var LeagueBase $externalSourceLeague */
        foreach ($externalSourceLeagues as $externalSourceLeague) {
            $externalId = $externalSourceLeague->getId();
            $leagueAttacher = $this->leagueAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($leagueAttacher === null) {
                $league = $this->createLeague($externalSource, $externalSourceLeague);
                if ($league === null) {
                    continue;
                }
                $leagueAttacher = new LeagueAttacher(
                    $league,
                    $externalSource,
                    $externalId
                );
                $this->leagueAttacherRepos->save($leagueAttacher);
            } else {
                $this->editLeague($leagueAttacher->getImportable(), $externalSourceLeague);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createLeague(ExternalSource $externalSource, LeagueBase $externalSourceLeague): ?LeagueBase
    {
        $association = $this->associationAttacherRepos->findImportable(
            $externalSource,
            $externalSourceLeague->getAssociation()->getId()
        );
        if ($association === null) {
            return null;
        }
        $league = new LeagueBase($association, $externalSourceLeague->getName());
        $this->leagueRepos->save($league);
        return $league;
    }

    protected function editLeague(LeagueBase $league, LeagueBase $externalSourceLeague)
    {
        $league->setName($externalSourceLeague->getName());
        $this->leagueRepos->save($league);
    }
}
