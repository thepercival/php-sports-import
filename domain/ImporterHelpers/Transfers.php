<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Psr\Log\LoggerInterface;
use Sports\Competition;
use Sports\Person;
use Sports\Person\Repository as PersonRepository;
use Sports\Team;
use Sports\Team\Player\Repository as PlayerRepository;
use Sports\Team\Repository as TeamRepository;
use Sports\Team\Role\Editor as RoleEditor;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\ExternalSource;
use SportsImport\Transfer;

class Transfers
{
    public function __construct(
        protected PersonRepository $personRepos,
        protected PersonAttacherRepository $personAttacherRepos,
        protected PlayerRepository $playerRepos,
        protected TeamRepository $teamRepos,
        protected TeamAttacherRepository $teamAttacherRepos,
        protected LoggerInterface $logger
    ) {
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
                $this->logger->warning('    transferDateTime(' . $dateAsString . ') outside season ' . $seasonPeriod);
                continue;
            }

            $person = $this->getPerson($externalSource, $externalTransfer->getPerson());
            if ($teamTo !== null) {
                $roleEditor->update(
                    $competition->getSeason(),
                    $person,
                    $externalTransfer->getDateTime(),
                    $teamTo,
                    $externalTransfer->getToLine()
                );
            } elseif ($teamFrom !== null) {
                $roleEditor->stop($competition->getSeason(), $person, $externalTransfer->getDateTime());
            }
            $this->personRepos->save($person, true);
            foreach ($person->getPlayers() as $player) {
                $this->playerRepos->save($player, true);
            }
        }
    }

    protected function getTeam(ExternalSource $externalSource, Team $externalTeam): Team|null
    {
        return $this->teamAttacherRepos->findImportable(
            $externalSource,
            (string)$externalTeam->getId()
        );
    }

    protected function getPerson(ExternalSource $externalSource, Person $externalPerson): Person
    {
        $person = $this->personAttacherRepos->findImportable(
            $externalSource,
            (string)$externalPerson->getId()
        );

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
