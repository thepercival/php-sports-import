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
use SportsImport\ExternalSource\Person as ExternalSourcePerson;

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

    public function importImage(
        ExternalSourcePerson $externalSourcePerson, ExternalSource $externalSource,
        PersonBase $person,
        string $localOutputPath, string $publicOutputPath, int $maxWidth = null
    ): bool
    {
        $personExternalId = $this->personAttacherRepos->findExternalId( $externalSource, $person );
        if( $personExternalId === null ) {
            return false;
        }
        $personImageId = substr( $personExternalId, strpos( $personExternalId, "/") + 1 );
        $localFilePath = $localOutputPath . $personImageId . ".png";

        if( file_exists( $localFilePath ) ) {
            $timestamp = filectime ( $localFilePath );
            $modifyDate = null;
            if( $timestamp !== false ) {
                $modifyDate = new \DateTimeImmutable( '@' . $timestamp );
            }
            if( $modifyDate !== null && $modifyDate->modify("+2 years") > (new \DateTimeImmutable()) ) {
                return false;
            }
        }

        try {
            $imgStream = $externalSourcePerson->getImagePerson( $personExternalId );
            $im = imagecreatefromstring($imgStream);
            if ($im === false) {
                return false;
            }
            if( $maxWidth !== null ) {
                // make smaller if greater than maxWidth
            }
            imagepng($im, $localFilePath);
            imagedestroy($im);

            $publicFilePath = $publicOutputPath . $personImageId . ".png";
            $person->setImageUrl( $publicFilePath );
            $this->personRepos->save( $person );
            return true;
        }
        catch( \Exception $e ) {

        }
        return false;
    }
}