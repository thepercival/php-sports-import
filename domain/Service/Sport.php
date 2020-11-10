<?php

namespace SportsImport\Service;

use SportsImport\ExternalSource;
use Sports\Sport\Repository as SportRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use Sports\Sport as SportBase;
use SportsImport\Attacher\Sport as SportAttacher;
use Psr\Log\LoggerInterface;

class Sport
{
    /**
     * @var SportRepository
     */
    protected $sportRepos;
    /**
     * @var SportAttacherRepository
     */
    protected $sportAttacherRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        SportRepository $sportRepos,
        SportAttacherRepository $sportAttacherRepos,
        LoggerInterface $logger/*,
        array $settings*/
    ) {
        $this->logger = $logger;
        $this->sportRepos = $sportRepos;
        $this->sportAttacherRepos = $sportAttacherRepos;
    }

    /**
     * @param ExternalSource $externalSource
     * @param array|SportBase[] $externalSourceSports
     * @throws \Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceSports)
    {
        foreach ($externalSourceSports as $externalSourceSport) {
            $externalId = $externalSourceSport->getId();
            $sportAttacher = $this->sportAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($sportAttacher === null) {
                $sport = $this->createSport($externalSourceSport);
                $sportAttacher = new SportAttacher(
                    $sport,
                    $externalSource,
                    $externalId
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
        $existingSport = $this->sportRepos->findOneBy( ["name" => $sport->getName()] );
        if( $existingSport !== null ) {
            return $existingSport;
        }
        $newSport = new SportBase($sport->getName());
        $newSport->setTeam($sport->getTeam());
        $this->sportRepos->save($newSport);
        return $newSport;
    }

    protected function editSport(SportBase $sport, SportBase $externalSourceSport)
    {
        $sport->setName($externalSourceSport->getName());
        $sport->setCustomId($externalSourceSport->getCustomId());
        $this->sportRepos->save($sport);
    }
}
