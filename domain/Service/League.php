<?php

namespace SportsImport\Service;

use SportsImport\ExternalSource;
use Sports\League\Repository as LeagueRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use Sports\League as LeagueBase;
use SportsImport\Attacher\League as LeagueAttacher;

class League
{
    public function __construct(
        protected LeagueRepository $leagueRepos,
        protected LeagueAttacherRepository $leagueAttacherRepos,
        protected AssociationAttacherRepository $associationAttacherRepos
    ) {
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
            (string)$externalSourceLeague->getAssociation()->getId()
        );
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
