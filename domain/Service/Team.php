<?php

namespace SportsImport\Service;

use \Exception;
use SportsImport\Attacher\Association\Repository as AssociationAttacherRepository;
use SportsImport\ExternalSource;
use Sports\Team\Repository as TeamRepository;
use SportsImport\Attacher\Team\Repository as TeamAttacherRepository;
use SportsImport\Attacher\Team as TeamAttacher;
use Psr\Log\LoggerInterface;
use Sports\Team as TeamBase;
use SportsImport\ExternalSource\Team as ExternalSourceTeam;

class Team
{
    /**
     * @var TeamRepository
     */
    protected $teamRepos;
    /**
     * @var TeamAttacherRepository
     */
    protected $teamAttacherRepos;
    /**
     * @var AssociationAttacherRepository
     */
    protected $associationAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        TeamRepository $teamRepos,
        TeamAttacherRepository $teamAttacherRepos,
        AssociationAttacherRepository $associationAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->teamRepos = $teamRepos;
        $this->teamAttacherRepos = $teamAttacherRepos;
        $this->associationAttacherRepos = $associationAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|TeamBase[] $externalSourceTeams
     * @throws Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceTeams)
    {
        $updated = 0; $added = 0;
        foreach ($externalSourceTeams as $externalSourceTeam) {
            $externalId = $externalSourceTeam->getId();
            $teamAttacher = $this->teamAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($teamAttacher === null) {
                $team = $this->createTeam($externalSource, $externalSourceTeam);
                if ($team === null) {
                    continue;
                }
                $teamAttacher = new TeamAttacher(
                    $team,
                    $externalSource,
                    $externalId
                );
                $this->teamAttacherRepos->save($teamAttacher);
                $added++;
            } else {
                $this->editTeam($teamAttacher->getImportable(), $externalSourceTeam);
                $updated++;
            }
        }
        $this->logger->info("added: " . $added . ", updated: " . $updated );
    }

    protected function createTeam(ExternalSource $externalSource, TeamBase $externalSourceTeam): ?TeamBase
    {
        $association = $this->associationAttacherRepos->findImportable(
            $externalSource,
            $externalSourceTeam->getAssociation()->getId()
        );
        if ($association === null) {
            return null;
        }
        $team = new TeamBase($association, $externalSourceTeam->getName());
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());
        $team->setImageUrl($externalSourceTeam->getImageUrl());

        $this->teamRepos->save($team);
        return $team;
    }

    protected function editTeam(TeamBase $team, TeamBase $externalSourceTeam)
    {
        $team->setName($externalSourceTeam->getName());
        $team->setAbbreviation($externalSourceTeam->getAbbreviation());
        $team->setImageUrl($externalSourceTeam->getImageUrl());
        $this->teamRepos->save($team);
    }

    public function importImage(
        ExternalSourceTeam $externalSourceTeam, ExternalSource $externalSource,
        TeamBase $team,
        string $localOutputPath, string $publicOutputPath, int $maxWidth = null
    ): bool
    {
        $teamExternalId = $this->teamAttacherRepos->findExternalId( $externalSource, $team );
        if( $teamExternalId === null ) {
            return false;
        }
        $localFilePath = $localOutputPath . $teamExternalId . ".png";

        if( file_exists( $localFilePath ) ) {
            $timestamp = filectime ( $localFilePath );
            $modifyDate = null;
            if( $timestamp !== false ) {
                $modifyDate = new \DateTimeImmutable( '@' . $timestamp );
            }
            if( $modifyDate !== null && $modifyDate->modify("+1 years") > (new \DateTimeImmutable()) ) {
                return false;
            }
        }

        try {
            $imgStream = $externalSourceTeam->getImageTeam( $teamExternalId );
            $im = imagecreatefromstring($imgStream);
            if ($im === false) {
                return false;
            }
            if( $maxWidth !== null ) {
                // make smaller if greater than maxWidth
            }
            imagepng($im, $localFilePath);
            imagedestroy($im);

            $publicFilePath = $publicOutputPath . $teamExternalId . ".png";
            $team->setImageUrl( $publicFilePath );
            $this->teamRepos->save( $team );
            return true;
        }
        catch( \Exception $e ) {

        }
        return false;
    }
}
