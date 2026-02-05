<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sports\Game\Against as AgainstGame;
use Sports\Person as PersonBase;
use Sports\Repositories\PersonRepository;
use Sports\Season;
use Sports\Sport\FootballLine;
use Sports\Team;
use Sports\Team\Role\Editor as RoleEditor;
use SportsImport\Attachers\PersonAttacher;
use SportsImport\Attachers\TeamAttacher;
use SportsImport\ExternalSource;
use SportsImport\Queue\Person\ImportEvents as ImportPersonEvents;
use SportsImport\Repositories\AttacherRepository;

final class Person
{
    protected ImportPersonEvents|null $importPersonEventsSender = null;
    /** @var AttacherRepository<PersonAttacher>  */
    protected AttacherRepository $personAttacherRepos;
    /** @var AttacherRepository<TeamAttacher>  */
    protected AttacherRepository $teamAttacherRepos;

    public function __construct(
        protected PersonRepository $personRepos,
        protected LoggerInterface $logger,
        protected EntityManagerInterface $entityManager,
    ) {
        $metaData = $entityManager->getClassMetadata(PersonAttacher::class);
        $this->personAttacherRepos = new AttacherRepository($entityManager, $metaData);

        $metaData = $entityManager->getClassMetadata(TeamAttacher::class);
        $this->teamAttacherRepos = new AttacherRepository($entityManager, $metaData);
    }

    public function setEventSender(ImportPersonEvents $importPersonEventsSender): void
    {
        $this->importPersonEventsSender = $importPersonEventsSender;
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
                $person = $this->importPerson($externalSource, $externalPerson, $season);

                $this->updatePlayerPeriods(
                    $externalSource,
                    $season,
                    $person,
                    $externalGame->getStartDateTime(),
                    $externalParticipation->getPlayer()->getTeam(),
                    $externalParticipation->getPlayer()->getLine(),
                    $externalParticipation->getPlayer()->getMarketValue(),
                );
            }
        }
    }

    public function importPerson(
        ExternalSource $externalSource,
        PersonBase $externalPerson,
        Season $season
    ): PersonBase {
        $externalId = $externalPerson->getId();
        if ($externalId === null) {
            throw new \Exception('no id for externalperson', E_ERROR);
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
            $this->entityManager->persist($personAttacher);
            $this->entityManager->flush();

            $this->importPersonEventsSender?->sendCreatePersonEvent($person, $season);
        } else {
            $person = $personAttacher->getImportable();
        }
        return $person;
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
        $this->entityManager->persist($person);
        $this->entityManager->flush();
        return $person;
    }

    protected function updatePlayerPeriods(
        ExternalSource $externalSource,
        Season $season,
        PersonBase $person,
        \DateTimeImmutable $gameDateTime,
        Team $externalTeam,
        int $line,
        int $marketValue
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

        $roleEditor = new RoleEditor($this->logger);
        $roleEditor->update($season, $person, $gameDateTime, $newTeam, $newLine, $marketValue);
    }
}
