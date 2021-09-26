<?php

namespace SportsImport\Service;

use Sports\Game\Against as AgainstGame;
use Sports\Person as PersonBase;
use Sports\Team\Player;
use Sports\Person\Repository as PersonRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Person as PersonAttacher;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use Sports\Team\Role\Combiner as RoleCombiner;
use SportsImport\ExternalSource;
use SportsImport\ExternalSource\Person as ExternalSourcePerson;

class Person
{
    public function __construct(
        protected PersonRepository $personRepos,
        protected PersonAttacherRepository $personAttacherRepos,
        protected TeamAttacherRepository $teamAttacherRepos
    ) {
    }

    public function importByAgainstGame(ExternalSource $externalSource, AgainstGame $externalGame): void
    {
        foreach ($externalGame->getParticipations() as $externalParticipation) {
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
        $dateOfBirth = $externalPerson->getDateOfBirth();
        if ($dateOfBirth !== null) {
            $person->setDateOfBirth($dateOfBirth);
        }
        return $this->personRepos->save($person);
    }

    protected function updatePlayerPeriods(
        ExternalSource $externalSource,
        PersonBase $person,
        Player $externalPlayer
    ): void {
        $teamAttacher = $this->teamAttacherRepos->findOneByExternalId(
            $externalSource,
            (string)$externalPlayer->getTeam()->getId()
        );
        if ($teamAttacher === null) {
            throw new \Exception('no team found for externalsource "'.$externalSource->getName().'" and extern teamid ' . (string)$externalPlayer->getTeam()->getId(), E_ERROR);
        }
        $newTeam = $teamAttacher->getImportable();
        $newLine = $externalPlayer->getLine();
        $newPeriod = $externalPlayer->getPeriod();

        $roleCombiner = new RoleCombiner($person);
        $roleCombiner->combineWithPast($newTeam, $newPeriod, $newLine);
    }

    public function importImage(
        ExternalSourcePerson $externalSourcePerson,
        ExternalSource $externalSource,
        PersonBase $person,
        string $localOutputPath,
        string $publicOutputPath,
        int $maxWidth = null
    ): bool {
        $personExternalId = $this->personAttacherRepos->findExternalId($externalSource, $person);
        if ($personExternalId === null) {
            return false;
        }
        $externalSeparatorPos = strpos($personExternalId, "/");
        if ($externalSeparatorPos === false) {
            return false;
        }
        $personImageId = substr($personExternalId, $externalSeparatorPos + 1);
        $localFilePath = $localOutputPath . $personImageId . ".png";

        if (file_exists($localFilePath)) {
            $timestamp = filectime($localFilePath);
            $modifyDate = null;
            if ($timestamp !== false) {
                $modifyDate = new \DateTimeImmutable('@' . $timestamp);
            }
            if ($modifyDate !== null && $modifyDate->modify("+2 years") > (new \DateTimeImmutable())) {
                return false;
            }
        }

        try {
            $imgStream = $externalSourcePerson->getImagePerson($personExternalId);
            $im = imagecreatefromstring($imgStream);
            if ($im === false) {
                return false;
            }
            if ($maxWidth !== null) {
                // make smaller if greater than maxWidth
            }
            imagepng($im, $localFilePath);
            imagedestroy($im);

            $publicFilePath = $publicOutputPath . $personImageId . ".png";
            $person->setImageUrl($publicFilePath);
            $this->personRepos->save($person);
            return true;
        } catch (\Exception $e) {
        }
        return false;
    }
}
