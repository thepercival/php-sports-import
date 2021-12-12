<?php

declare(strict_types=1);

namespace SportsImport\ImporterHelpers;

use Sports\Person as PersonBase;
use Sports\Team\Player as TeamPlayer;
use Sports\Person\Repository as PersonRepository;
use SportsImport\Attacher\Person\Repository as PersonAttacherRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\ExternalSource;

class Player
{
    public function __construct(
        protected PersonRepository $personRepos,
        protected PersonAttacherRepository $personAttacherRepos,
        protected TeamAttacherRepository $teamAttacherRepos
    ) {
    }

    public function importImage(
        ExternalSource\CompetitionDetails $externalSourcePlayer,
        ExternalSource $externalSource,
        TeamPlayer $player,
        string $localOutputPath
    ): bool {
        $person = $player->getPerson();

        $imageFile = (string)$player->getId() . '.png';
        $localImagePath = $localOutputPath . $imageFile;

        try {
            if (!$this->renewLocalImageOnDisk($localImagePath)) {
                return false;
            }
            $personExternalId = $this->getPersonExternalNumberId($externalSource, $person);
            if ($personExternalId === false) {
                return false;
            }
            return $this->getImageAndSaveOnDisk($externalSourcePlayer, $personExternalId, $localImagePath);
        } catch (\Exception $e) {
        }
        return false;
    }

    protected function getPersonExternalNumberId(ExternalSource $externalSource, PersonBase $person): string|false
    {
        $personExternalId = $this->personAttacherRepos->findExternalId($externalSource, $person);
        if ($personExternalId === null) {
            return false;
        }
        $externalSeparatorPos = strpos($personExternalId, "/");
        if ($externalSeparatorPos === false) {
            return false;
        }
        return substr($personExternalId, $externalSeparatorPos + 1);
    }

    protected function renewLocalImageOnDisk(string $localImagePath): bool
    {
        if (!file_exists($localImagePath)) {
            return true;
        }
        $timestamp = filectime($localImagePath);
        if ($timestamp === false) {
            return true;
        }
        $modifyDate = new \DateTimeImmutable('@' . $timestamp);
        return $modifyDate->modify("+1 years") < (new \DateTimeImmutable());
    }

    protected function getImageAndSaveOnDisk(
        ExternalSource\CompetitionDetails $externalSourcePlayer,
        string $personExternalId,
        string $localFilePath,
    ): bool {
        $imgStream = $externalSourcePlayer->getImagePlayer($personExternalId);
        $im = imagecreatefromstring($imgStream);
        if ($im === false) {
            return false;
        }
        // @TODO CDK
//            if ($maxWidth !== null) {
//                // make smaller if greater than maxWidth
//            }
        imagepng($im, $localFilePath);
        imagedestroy($im);
        return true;
    }
}
