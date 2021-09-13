<?php

namespace SportsImport\Service;

use SportsImport\ExternalSource;
use Sports\Sport\Repository as SportRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use Sports\Sport as SportBase;
use SportsImport\Attacher\Sport as SportAttacher;

class Sport
{
    public function __construct(
        protected SportRepository $sportRepos,
        protected SportAttacherRepository $sportAttacherRepos
    ) {
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|SportBase[] $externalSourceSports
     * @throws \Exception
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
                $externalId
            );
            if ($sportAttacher === null) {
                $sport = $this->createSport($externalSourceSport);
                $sportAttacher = new SportAttacher(
                    $sport,
                    $externalSource,
                    (string)$externalId
                );
                $this->sportAttacherRepos->save($sportAttacher);
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
        return $this->sportRepos->save($newSport);
    }

    protected function editSport(SportBase $sport, SportBase $externalSourceSport): SportBase
    {
        $sport->setName($externalSourceSport->getName());
        $sport->setCustomId($externalSourceSport->getCustomId());
        return $this->sportRepos->save($sport);
    }
}
