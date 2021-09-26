<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Competition as CompetitionBase;
use Sports\Person;
use Sports\Team\Role as TeamRole;
use stdClass;
use SportsImport\ExternalSource as ExternalSourceBase;
use SportsImport\ExternalSource\Implementation as ExternalSourceImplementation;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use Psr\Log\LoggerInterface;

use Sports\Sport;
use SportsImport\ExternalSource\Sport as ExternalSourceSport;
use SportsImport\ExternalSource\Association as ExternalSourceAssociation;
use Sports\Association;
use SportsImport\ExternalSource\Season as ExternalSourceSeason;
use Sports\Season;
use SportsImport\ExternalSource\League as ExternalSourceLeague;
use Sports\League;
use SportsImport\ExternalSource\Competition as ExternalSourceCompetition;
use Sports\Competition;
use SportsImport\ExternalSource\Team as ExternalSourceTeam;
use Sports\Team;
use SportsImport\ExternalSource\Competitor\Team as ExternalSourceTeamCompetitor;
use Sports\Competitor\Team as TeamCompetitor;
use SportsImport\ExternalSource\Structure as ExternalSourceStructure;
use Sports\Structure;
use SportsImport\ExternalSource\Game\Against as ExternalSourceAgainstGame;
use SportsImport\ExternalSource\Team\Role as ExternalSourceTeamRole;
use SportsImport\ExternalSource\Person as ExternalSourcePerson;
use Sports\Game\Against as AgainstGame;

class SofaScore implements
    ExternalSourceImplementation,
    ExternalSourceSport,
    ExternalSourceAssociation,
    ExternalSourceSeason,
    ExternalSourceLeague,
    ExternalSourceCompetition,
    ExternalSourceTeam,
    // ExternalSourceTeamRole,
    ExternalSourcePerson,
    ExternalSourceTeamCompetitor,
    ExternalSourceStructure,
    ExternalSourceAgainstGame,
    CacheInfo,
    ApiHelper,
    Proxy
{
    public const NAME = "SofaScore";
    protected SofaScore\ApiHelper $apiHelper;

    protected SofaScore\Helper\Association $associationHelper;
    protected SofaScore\Helper\Competition $competitionHelper;
    protected SofaScore\Helper\League $leagueHelper;
    protected SofaScore\Helper\Person $personHelper;
    protected SofaScore\Helper\Season $seasonHelper;
    protected SofaScore\Helper\Sport $sportHelper;
    protected SofaScore\Helper\Structure $structureHelper;
    protected SofaScore\Helper\Team $teamHelper;
    protected SofaScore\Helper\Competitor\Team $teamCompetitorHelper;
    protected SofaScore\Helper\Game\Against $againstGameHelper;
    protected SofaScore\Helper\Team\Role $teamRoleHelper;

    public function __construct(
        protected ExternalSourceBase $externalSource,
        protected CacheItemDbRepository $cacheItemDbRepos,
        protected LoggerInterface $logger
    ) {
        $apiHelper = new SofaScore\ApiHelper($externalSource, $cacheItemDbRepos, $this->logger);
        $this->apiHelper = $apiHelper;
        $this->associationHelper = new SofaScore\Helper\Association($this, $apiHelper, $this->logger);
        $this->competitionHelper = new SofaScore\Helper\Competition($this, $apiHelper, $this->logger);
        $this->leagueHelper = new SofaScore\Helper\League($this, $apiHelper, $this->logger);
        $this->personHelper = new SofaScore\Helper\Person($this, $apiHelper, $this->logger);
        $this->seasonHelper = new SofaScore\Helper\Season($this, $apiHelper, $this->logger);
        $this->sportHelper = new SofaScore\Helper\Sport($this, $apiHelper, $this->logger);
        $this->structureHelper = new SofaScore\Helper\Structure($this, $apiHelper, $this->logger);
        $this->teamHelper = new SofaScore\Helper\Team($this, $apiHelper, $this->logger);
        $this->teamCompetitorHelper = new SofaScore\Helper\Competitor\Team($this, $apiHelper, $this->logger);
        $this->againstGameHelper = new SofaScore\Helper\Game\Against($this, $apiHelper, $this->logger);
        $this->teamRoleHelper = new SofaScore\Helper\Team\Role($this, $apiHelper, $this->logger);

        $this->cacheItemDbRepos = $cacheItemDbRepos;
        /* $this->structureOptions = new StructureOptions(
             new VoetbalRange(1, 32),
             new VoetbalRange( 2, 256),
             new VoetbalRange( 2, 30)
         );*/
    }

    /*protected function getErrorUrl(): string
    {
        reset( $this->settings['www']['urls']);
    }*/

    public function getExternalSource(): ExternalSourceBase
    {
        return $this->externalSource;
    }

    /**
     * @return array<int|string, Sport>
     */
    public function getSports(): array
    {
        return $this->sportHelper->getSports();
    }

    public function getSport(string|int $id): Sport|null
    {
        return $this->sportHelper->getSport($id);
    }

    /**
     * @return array<int|string, Association>
     */
    public function getAssociations(Sport $sport): array
    {
        return $this->associationHelper->getAssociations($sport);
    }

    public function getAssociation(Sport $sport, string|int $id): Association|null
    {
        return $this->associationHelper->getAssociation($sport, $id);
    }

    /**
     * @return array<int|string, Season>
     */
    public function getSeasons(): array
    {
        return $this->seasonHelper->getSeasons();
    }

    public function getSeason(string|int $id): ?Season
    {
        return $this->seasonHelper->getSeason($id);
    }

    /**
     * @param Association $association
     * @return array<int|string, League>
     */
    public function getLeagues(Association $association): array
    {
        return $this->leagueHelper->getLeagues($association);
    }

    public function getLeague(Association $association, string|int $id): League|null
    {
        return $this->leagueHelper->getLeague($association, $id);
    }

    /**
     * @param Sport $sport
     * @param League $league
     * @return array<int|string, CompetitionBase>
     */
    public function getCompetitions(Sport $sport, League $league): array
    {
        return $this->competitionHelper->getCompetitions($sport, $league);
    }

    /**
     * @param Sport $sport
     * @param League $league
     * @param Season $season
     * @return CompetitionBase|null
     */
    public function getCompetition(Sport $sport, League $league, Season $season): ?CompetitionBase
    {
        return $this->competitionHelper->getCompetition($sport, $league, $season);
    }

    /**
     * @return list<Team>
     */
    public function getTeams(Competition $competition): array
    {
        return $this->teamHelper->getTeams($competition);
    }

    public function getTeam(Competition $competition, string|int $id): Team|null
    {
        return $this->teamHelper->getTeam($competition, $id);
    }

    public function getImageTeam(string $teamExternalId): string
    {
        return $this->teamHelper->getImageTeam($teamExternalId);
    }

    /**
     * @return list<TeamCompetitor>
     */
    public function getTeamCompetitors(Competition $competition): array
    {
        return $this->teamCompetitorHelper->getTeamCompetitors($competition);
    }

    public function getTeamCompetitor(Competition $competition, string|int $id): ?TeamCompetitor
    {
        return $this->teamCompetitorHelper->getTeamCompetitor($competition, $id);
    }

    public function getStructure(Competition $competition): ?Structure
    {
        return $this->structureHelper->getStructure($competition);
    }

    public function getBatchNrs(Competition $competition): array
    {
        return $this->againstGameHelper->getBatchNrs($competition);
    }

    public function getAgainstGames(Competition $competition, int $batchNr): array
    {
        return $this->againstGameHelper->getAgainstGames($competition, $batchNr);
    }

    public function getAgainstGame(Competition $competition, string|int $id): AgainstGame|null
    {
        return $this->againstGameHelper->getAgainstGame($competition, $id);
    }

//    public function convertToTeamRole( Game $game, Team $team, stdClass $externalTeamRole): TeamRole {
//        return $this->getTeamRoleHelper()->convertToTeamRole( $game, $team, $externalTeamRole );
//    }

    public function getPerson(AgainstGame $againstGame, string|int $id): Person|null
    {
        return $this->personHelper->getPerson($againstGame, $id);
    }

    public function convertToPerson(stdClass $externalPerson): ?Person
    {
        return $this->personHelper->convertToPerson($externalPerson);
    }

    public function getImagePerson(string $personExternalId): string
    {
        return $this->personHelper->getImagePerson($personExternalId);
    }

    public function getEndPoint(int $dataTypeIdentifier = null): string
    {
        return $this->apiHelper->getEndPoint($dataTypeIdentifier);
    }

    public function getEndPointSuffix(int $dataTypeIdentifier): string
    {
        return $this->apiHelper->getEndPointSuffix($dataTypeIdentifier);
    }

    public function getCacheMinutes(int $dataTypeIdentifier): int
    {
        return $this->apiHelper->getCacheMinutes($dataTypeIdentifier);
    }

    public function getCacheId(int $dataTypeIdentifier): string
    {
        return $this->apiHelper->getCacheId($dataTypeIdentifier);
    }

    public function getCacheInfo(int $dataTypeIdentifier = null): string
    {
        return $this->apiHelper->getCacheInfo($dataTypeIdentifier);
    }

    /**
     * @param array<string, string> $options
     */
    public function setProxy(array $options): void
    {
        $this->apiHelper->setProxy($options);
    }
}
