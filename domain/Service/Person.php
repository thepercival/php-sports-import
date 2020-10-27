<?php

namespace SportsImport\Service;

use League\Period\Period;
use Sports\Game;
use Sports\Person as PersonBase;
use Sports\Team\Player;
use Sports\Person\Repository as PersonRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Person as PersonAttacher;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use Sports\Team\Role\Combiner as RoleCombiner;
use SportsImport\ExternalSource;

class Person {
    protected PersonRepository $personRepos;
    protected PersonAttacherRepository $personAttacherRepos;
    protected TeamAttacherRepository $teamAttacherRepos;

    public function __construct(
        PersonRepository $personRepos,
        PersonAttacherRepository $personAttacherRepos,
        TeamAttacherRepository $teamAttacherRepos
    ) {
        $this->personRepos = $personRepos;
        $this->personAttacherRepos = $personAttacherRepos;
        $this->teamAttacherRepos = $teamAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param Game $externalGame
     * @throws \Exception
     */
    public function importByGame( ExternalSource $externalSource, Game $externalGame)
    {
        foreach ($externalGame->getParticipations() as $externalParticipation) {
            $externalPerson = $externalParticipation->getPlayer()->getPerson();

            $externalId = $externalPerson->getId();
            $personAttacher = $this->personAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            $person = null;
            if ($personAttacher === null) {
                $person = $this->createPerson($externalPerson);
                $personAttacher = new PersonAttacher(
                    $person,
                    $externalSource,
                    $externalId
                );
                $this->personAttacherRepos->save($personAttacher);
            } else {
                $person = $personAttacher->getImportable();
            }
            $this->updatePlayerPeriods($externalSource, $person, $externalParticipation->getPlayer());
        }
    }

    protected function createPerson(PersonBase $externalPerson): PersonBase
    {
        $person = new PersonBase(
            $externalPerson->getFirstName(),
            $externalPerson->getNameInsertion(),
            $externalPerson->getLastName()
        );
        $person->setDateOfBirth( $externalPerson->getDateOfBirth() );


        $this->personRepos->save($person);
        return $person;
    }

    protected function updatePlayerPeriods(
        ExternalSource $externalSource,
        PersonBase $person,
        Player $externalPlayer
        )
    {
        $teamAttacher = $this->teamAttacherRepos->findOneByExternalId(
            $externalSource,
            $externalPlayer->getTeam()->getId()
        );
        if( $teamAttacher === null ) {
            throw new \Exception("no team found for externalsource \"".$externalSource->getName()."\" and extern teamid " . $externalPlayer->getTeam()->getId(), E_ERROR );
        }
        $newTeam = $teamAttacher->getImportable();
        $newLine = $externalPlayer->getLine();
        $newPeriod = $externalPlayer->getPeriod();

        $roleCombiner = new RoleCombiner( $person );
        $roleCombiner->combineWithPast( $newTeam, $newPeriod, $newLine );
    }
}