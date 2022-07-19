<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Sports\Game\Against as AgainstGame;
use Sports\Person as PersonBase;
use Sports\Sport\FootballLine;
use Sports\Season;
use Sports\Team;
use Sports\Person\Repository as PersonRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Person as PersonAttacher;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use Sports\Team\Role\Editor as RoleEditor;
use SportsImport\ExternalSource;

class Person
{
    public function __construct(
        protected PersonRepository $personRepos,
        protected PersonAttacherRepository $personAttacherRepos,
        protected TeamAttacherRepository $teamAttacherRepos
    ) {
    }

    public function importByAgainstGame(ExternalSource $externalSource, Season $season, AgainstGame $externalGame): void
    {
        foreach ($externalGame->getPlaces() as $externalGamePlace) {
            foreach ($externalGamePlace->getParticipations() as $externalParticipation) {
                $externalPerson = $externalParticipation->getPlayer()->getPerson();

                $externalId = $externalPerson->getId();
                if ($externalId === null) {
                    continue;
                }
                $personAttacher = $this->personAttacherRepos->findOneByExternalId(
                    $externalSource,
                    (string)$externalId
                );
                if ($personAttacher === null) {
                    $person = $this->createPerson($externalPerson);
                    $personAttacher = new PersonAttacher(
                        $person,
                        $externalSource,
                        (string)$externalId
                    );
                    $this->personAttacherRepos->save($personAttacher);
                } else {
                    $person = $personAttacher->getImportable();
                }

                $this->updatePlayerPeriods(
                    $externalSource,
                    $season,
                    $person,
                    $externalGame->getStartDateTime(),
                    $externalParticipation->getPlayer()->getTeam(),
                    $externalParticipation->getPlayer()->getLine()
                );
            }
        }
    }

    protected function createPerson(PersonBase $externalPerson): PersonBase
    {
        $person = new PersonBase(
            $externalPerson->getFirstName(),
            $externalPerson->getNameInsertion(),
            $externalPerson->getLastName()
        );
        $dateOfBirth = $externalPerson->getDateOfBirth();
        if ($dateOfBirth !== null) {
            $person->setDateOfBirth($dateOfBirth);
        }
        return $this->personRepos->save($person);
    }

    protected function updatePlayerPeriods(
        ExternalSource $externalSource,
        Season $season,
        PersonBase $person,
        \DateTimeImmutable $gameDateTime,
        Team $externalTeam,
        int $line
    ): void {
        $externalTeamId = (string)$externalTeam->getId();
        $teamAttacher = $this->teamAttacherRepos->findOneByExternalId(
            $externalSource,
            $externalTeamId
        );
        if ($teamAttacher === null) {
            throw new \Exception('no team found for externalsource "'.$externalSource->getName().'" and extern teamid ' . $externalTeamId, E_ERROR);
        }
        $newTeam = $teamAttacher->getImportable();
        $newLine = FootballLine::from($line);

        $roleEditor = new RoleEditor();
        $roleEditor->update($season, $person, $gameDateTime, $newTeam, $newLine);
    }
}
