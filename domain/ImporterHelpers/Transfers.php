<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Person;
use Sports\Repositories\PersonRepository;
use Sports\Repositories\TeamPlayerRepository;
use Sports\Team;
use Sports\Team\Role\Editor as RoleEditor;
use SportsImport\ExternalSource;
use SportsImport\Repositories\AttacherRepository;
use SportsImport\Transfer;
use SportsImport\Attachers\TeamAttacher;
use SportsImport\Attachers\PersonAttacher;

/**
 * @api
 */
final class Transfers
{
    /** @var AttacherRepository<PersonAttacher>  */
    protected AttacherRepository $personAttacherRepos;
    /** @var AttacherRepository<TeamAttacher>  */
    protected AttacherRepository $teamAttacherRepos;

    public function __construct(
//        protected PersonRepository $personRepos,
//        protected TeamPlayerRepository $teamPlayerRepos,
        protected LoggerInterface $logger,
        protected EntityManagerInterface $entityManager,
    ) {
        $metadata = $entityManager->getClassMetadata(PersonAttacher::class);
        $this->personAttacherRepos = new AttacherRepository($entityManager, $metadata);

        $metadata = $entityManager->getClassMetadata(TeamAttacher::class);
        $this->teamAttacherRepos = new AttacherRepository($entityManager, $metadata);
    }

    /**
     * @param ExternalSource $externalSource
     * @param Competition $competition
     * @param list<Transfer> $externalTransfers
     */
    public function import(ExternalSource $externalSource, Competition $competition, array $externalTransfers): void
    {
        $roleEditor = new RoleEditor($this->logger);
        $seasonPeriod = $competition->getSeason()->getPeriod();
        foreach ($externalTransfers as $externalTransfer) {
            $teamFrom = $this->getTeam($externalSource, $externalTransfer->getFromTeam());

            $dateAsString = $externalTransfer->getDateTime()->format(\DateTimeInterface::ISO8601);
            $this->logger->info(
                'transfer for "' . $externalTransfer->getPerson()->getName() . '" ('
                . $externalTransfer->getToLine()->name . ') at ' . $dateAsString
            );

            if ($teamFrom === null) {
                $this->logger->info(
                    '    from:no team found for externalsource "' . $externalSource->getName(
                    ) . '" and extern teamid ' . (string)$externalTransfer->getFromTeam()->getId(
                    ) . '("' . $externalTransfer->getFromTeam()->getName() . '")'
                );
            } else {
                $this->logger->info('   from team: "' . $teamFrom->getName());
            }

            $teamTo = $this->getTeam($externalSource, $externalTransfer->getToTeam());
            if ($teamTo === null) {
                $this->logger->info(
                    '    to:no team found for externalsource "' . $externalSource->getName(
                    ) . '" and extern teamid ' . (string)$externalTransfer->getToTeam()->getId(
                    ) . '("' . $externalTransfer->getToTeam()->getName() . '")'
                );
            } else {
                $this->logger->info('   to team: "' . $teamTo->getName());
            }
            if (!$roleEditor->withInPeriod($seasonPeriod, $externalTransfer->getDateTime())) {
                $this->logger->warning('    transferDateTime(' . $dateAsString . ') outside season ' . $seasonPeriod->toIso8601());
                continue;
            }

            $person = $this->getPerson($externalSource, $externalTransfer->getPerson());
            if ($teamTo !== null) {
                $roleEditor->update(
                    $competition->getSeason(),
                    $person,
                    $externalTransfer->getDateTime(),
                    $teamTo,
                    $externalTransfer->getToLine(),
                    0
                );
            } elseif ($teamFrom !== null) {
                $roleEditor->stop($competition->getSeason(), $person, $externalTransfer->getDateTime());
            }
            $this->entityManager->persist($person);
            $this->entityManager->flush();
            foreach ($person->getPlayers() as $player) {
                $this->entityManager->persist($player);
            }
            $this->entityManager->flush();
        }
    }

    protected function getTeam(ExternalSource $externalSource, Team $externalTeam): Team|null
    {
        $attacher = $this->teamAttacherRepos->findOneByExternalId($externalSource, (string)$externalTeam->getId());
        if ($attacher === null) {
            return null;
        }
        return $attacher->getImportable();
    }

    protected function getPerson(ExternalSource $externalSource, Person $externalPerson): Person
    {
        $attacher = $this->personAttacherRepos->findOneByExternalId($externalSource, (string)$externalPerson->getId());
        $person = $attacher?->getImportable();

        if ($person === null) {
            throw new \Exception('person not found', E_ERROR);
        }
        return $person;
    }

//    protected function importPerson(PlayerData $playerData): PersonBase
//    {
//        $person = $this->personHelper->convertDataToPerson($playerData);
//        return $this->personRepos->save($person);
//    }

//    protected function createPerson(PersonBase $externalPerson): PersonBase
//    {
//        $person = new PersonBase(
//            $externalPerson->getFirstName(),
//            $externalPerson->getNameInsertion(),
//            $externalPerson->getLastName()
//        );
//        $dateOfBirth = $externalPerson->getDateOfBirth();
//        if ($dateOfBirth !== null) {
//            $person->setDateOfBirth($dateOfBirth);
//        }
//        return $this->personRepos->save($person);
//    }
//
}
