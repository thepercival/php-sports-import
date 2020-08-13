<?php

namespace SportsImport\Service;

use SportsImport\ImporterInterface;
use SportsImport\ExternalSource;
use Sports\Competition\Repository as CompetitionRepository;
use SportsImport\Attacher\Competition\Repository as CompetitionAttacherRepository;
use SportsImport\Attacher\League\Repository as LeagueAttacherRepository;
use SportsImport\Attacher\Season\Repository as SeasonAttacherRepository;
use SportsImport\Attacher\Sport\Repository as SportAttacherRepository;
use Sports\Sport\Config\Service as SportConfigService;
use Sports\Competition as CompetitionBase;
use SportsImport\Attacher\Competition as CompetitionAttacher;
use Psr\Log\LoggerInterface;

class Competition implements ImporterInterface
{
    /**
     * @var CompetitionRepository
     */
    protected $competitionRepos;
    /**
     * @var CompetitionAttacherRepository
     */
    protected $competitionAttacherRepos;
    /**
     * @var LeagueAttacherRepository
     */
    protected $leagueAttacherRepos;
    /**
     * @var SeasonAttacherRepository
     */
    protected $seasonAttacherRepos;
    /**
     * @var SportAttacherRepository
     */
    protected $sportAttacherRepos;
    /**
     * @var SportConfigService
     */
    protected $sportConfigService;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CompetitionRepository $competitionRepos,
        CompetitionAttacherRepository $competitionAttacherRepos,
        LeagueAttacherRepository $leagueAttacherRepos,
        SeasonAttacherRepository $seasonAttacherRepos,
        SportAttacherRepository $sportAttacherRepos,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->competitionRepos = $competitionRepos;
        $this->competitionAttacherRepos = $competitionAttacherRepos;
        $this->leagueAttacherRepos = $leagueAttacherRepos;
        $this->seasonAttacherRepos = $seasonAttacherRepos;
        $this->sportAttacherRepos = $sportAttacherRepos;
        $this->sportConfigService = new SportConfigService();
    }

    /**
     * @param ExternalSource $externalSource
     * @param array $externalSourceCompetitions
     * @throws \Exception
     */
    public function import(ExternalSource $externalSource, array $externalSourceCompetitions)
    {
        /** @var CompetitionBase $externalSourceCompetition */
        foreach ($externalSourceCompetitions as $externalSourceCompetition) {
            $externalId = $externalSourceCompetition->getId();
            $competitionAttacher = $this->competitionAttacherRepos->findOneByExternalId(
                $externalSource,
                $externalId
            );
            if ($competitionAttacher === null) {
                $competition = $this->createCompetition($externalSource, $externalSourceCompetition);
                if ($competition === null) {
                    continue;
                }
                $competitionAttacher = new CompetitionAttacher(
                    $competition,
                    $externalSource,
                    $externalId
                );
                $this->competitionAttacherRepos->save($competitionAttacher);
            } else {
                $this->editCompetition($competitionAttacher->getImportable(), $externalSourceCompetition);
            }
        }
        // bij syncen hoeft niet te verwijderden
    }

    protected function createCompetition(ExternalSource $externalSource, CompetitionBase $externalSourceCompetition): ?CompetitionBase
    {
        $league = $this->leagueAttacherRepos->findImportable(
            $externalSource,
            $externalSourceCompetition->getLeague()->getId()
        );
        if ($league  === null) {
            return null;
        }
        $season = $this->seasonAttacherRepos->findImportable(
            $externalSource,
            $externalSourceCompetition->getSeason()->getId()
        );
        if ($season  === null) {
            return null;
        }
        $existingCompetition = $this->competitionRepos->findOneBy( [
            "league" => $league, "season" => $season
        ]);
        if( $existingCompetition !== null ) {
            return $existingCompetition;
        }

        $competition = new CompetitionBase($league, $season);
        $competition->setStartDateTime($season->getStartDateTime());

        foreach ($externalSourceCompetition->getSportConfigs() as $externalSourceSportConfig) {
            $sport = $this->sportAttacherRepos->findImportable($externalSource, $externalSourceSportConfig->getSport()->getId());
            if ($sport === null) {
                continue;
            }
            $sportConfig = $this->sportConfigService->copy($externalSourceSportConfig, $competition, $sport);
        }
        $this->competitionRepos->customPersist($competition);
        $this->competitionRepos->save($competition);
        return $competition;
    }

    protected function editCompetition(CompetitionBase $competition, CompetitionBase $externalSourceCompetition)
    {
        // $competition->setName($externalSourceCompetition->getName());
        // $this->competitionRepos->save($competition);
    }
}
