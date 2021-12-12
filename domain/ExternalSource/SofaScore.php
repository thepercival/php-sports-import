<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Competition as CompetitionBase;
use Sports\Person;
use Sports\Team\Role as TeamRole;
use stdClass;
use SportsImport\ExternalSource as ExternalSourceBase;
use SportsImport\ExternalSource;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use Psr\Log\LoggerInterface;

use SportsImport\ExternalSource\SofaScore\ApiHelper\Sport as SportApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Sport as SportHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Association as AssociationApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Association as AssociationHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\League as LeagueApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\League as LeagueHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Season as SeasonApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Season as SeasonHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Competition as CompetitionApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Competition as CompetitionHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Structure as StructureApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Structure as StructureHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Team as TeamApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Team as TeamHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Competitor\Team as TeamCompetitorApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Competitor\Team as TeamCompetitorHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGames as AgainstGamesApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameDetails as AgainstGameDetailsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameLineups as AgainstGameLineupsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameEvents as AgainstGameEventsApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Game\Against as AgainstGameHelper;
//use SportsImport\ExternalSource\SofaScore\ApiHelper\Team\Role as TeamRoleApiHelper;
//use SportsImport\ExternalSource\SofaScore\Helper\Team\Role as TeamRoleHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Player as PlayerApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Person as PersonHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\GameRoundNumbers as GameRoundNumbersApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Game\RoundNumbers as GameRoundNumbersHelper;

use Sports\Sport;
use Sports\Association;
use Sports\Season;
use Sports\League;
use Sports\Competition;
use Sports\Team;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Structure;
use Sports\Game\Against as AgainstGame;

class SofaScore implements
    Implementation,
    Competitions,
    CompetitionStructure,
    CompetitionDetails,
    Proxy
{
    public const NAME = "SofaScore";
    /**
     * @var array<string, string>|null
     */
    private array|null $proxyOptions = null;

    protected SportHelper $sportHelper;
    protected AssociationHelper $associationHelper;
    protected LeagueHelper $leagueHelper;
    protected SeasonHelper $seasonHelper;
    protected CompetitionHelper $competitionHelper;

    protected PersonHelper $personHelper;
    protected SofaScore\Helper\Structure $structureHelper;
    protected SofaScore\Helper\Team $teamHelper;
    protected SofaScore\Helper\Competitor\Team $teamCompetitorHelper;
    protected SofaScore\Helper\Game\Against $againstGameHelper;
    protected SofaScore\Helper\Player $playerHelper;
    // protected SofaScore\Helper\Team\Role $teamRoleHelper;

    protected SofaScore\Helper\Game\RoundNumbers $gameRoundsHelper;

    public function __construct(
        protected ExternalSourceBase $externalSource,
        protected CacheItemDbRepository $cacheItemDbRepos,
        protected LoggerInterface $logger
    ) {
        $sportApiHelper = new SportApiHelper($this, $cacheItemDbRepos, $logger);
        $this->sportHelper = new SportHelper($sportApiHelper, $this, $this->logger);

        $associationApiHelper = new AssociationApiHelper($this, $cacheItemDbRepos, $logger);
        $this->associationHelper = new AssociationHelper($associationApiHelper, $this, $this->logger);

        $seasonApiHelper = new SeasonApiHelper($this, $cacheItemDbRepos, $logger);
        $this->seasonHelper = new SeasonHelper($seasonApiHelper, $this, $this->logger);

        $leagueApiHelper = new LeagueApiHelper($this, $cacheItemDbRepos, $logger);
        $this->leagueHelper = new LeagueHelper($leagueApiHelper, $this, $this->logger);

        $competitionApiHelper = new CompetitionApiHelper($this, $cacheItemDbRepos, $logger);
        $this->competitionHelper = new CompetitionHelper($competitionApiHelper, $this, $this->logger);

        $teamApiHelper = new TeamApiHelper($this, $cacheItemDbRepos, $logger);
        $this->teamHelper = new TeamHelper($teamApiHelper, $this, $this->logger);

        $teamCompetitorApiHelper = new TeamCompetitorApiHelper($teamApiHelper, $this, $cacheItemDbRepos, $logger);
        $this->teamCompetitorHelper = new TeamCompetitorHelper(
            $this->teamHelper,
            $teamCompetitorApiHelper,
            $this,
            $this->logger
        );

        $this->structureHelper = new StructureHelper($teamCompetitorApiHelper, $this, $this->logger);

        $playerApiHelper = new PlayerApiHelper($this, $cacheItemDbRepos, $logger);
        $this->personHelper = new PersonHelper($playerApiHelper, $this, $this->logger);
        $this->playerHelper = new SofaScore\Helper\Player($playerApiHelper, $this, $this->logger);

        $againstGameLineupsApiHelper = new AgainstGameLineupsApiHelper($playerApiHelper, $this, $cacheItemDbRepos, $logger);
        $againstGameEventsApiHelper = new AgainstGameEventsApiHelper($playerApiHelper, $this, $cacheItemDbRepos, $logger);
        $againstGameDetailsApiHelper = new AgainstGameDetailsApiHelper(
            $againstGameLineupsApiHelper,
            $againstGameEventsApiHelper,
            $teamApiHelper,
            $this,
            $cacheItemDbRepos,
            $logger
        );
        $againstGamesApiHelper = new AgainstGamesApiHelper($againstGameDetailsApiHelper, $this, $cacheItemDbRepos, $logger);


        $this->againstGameHelper = new AgainstGameHelper(
            $this->teamHelper,
            $this->personHelper,
            $againstGamesApiHelper,
            $againstGameDetailsApiHelper,
            $againstGameLineupsApiHelper,
            $againstGameEventsApiHelper,
            $playerApiHelper,
            $this,
            $this->logger
        );

        $gameRoundNumbersApiHelper = new GameRoundNumbersApiHelper($this, $cacheItemDbRepos, $logger);
        $this->gameRoundsHelper = new GameRoundNumbersHelper(
            $gameRoundNumbersApiHelper,
            $this,
            $this->logger
        );

//        $teamRoleApiHelper = new TeamRoleApiHelper($externalSource, $cacheItemDbRepos, $logger);
//        $this->teamRoleHelper = new TeamRoleHelper($teamRoleApiHelper, $this, $this->logger);



        $this->cacheItemDbRepos = $cacheItemDbRepos;
        /* $this->structureOptions = new StructureOptions(
             new VoetbalRange(1, 32),
             new VoetbalRange( 2, 256),
             new VoetbalRange( 2, 30)
         );*/
    }

    public function getExternalSource(): ExternalSource
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

    public function getStructure(Competition $competition): Structure
    {
        return $this->structureHelper->getStructure($competition);
    }

    /**
     * @param Competition $competition
     * @return list<int>
     */
    public function getGameRoundNumbers(Competition $competition): array
    {
        return $this->gameRoundsHelper->getGameRoundNumbers($competition);
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @return array<int|string, AgainstGame>
     * @throws \Exception
     */
    public function getAgainstGames(Competition $competition, int $gameRoundNumber): array
    {
        return $this->againstGameHelper->getAgainstGames($competition, $gameRoundNumber);
    }

    public function getAgainstGame(Competition $competition, string|int $id, bool $removeFromGameCache): AgainstGame|null
    {
        return $this->againstGameHelper->getAgainstGame($competition, $id, $removeFromGameCache);
    }

//    public function convertToTeamRole( Game $game, Team $team, stdClass $externalTeamRole): TeamRole {
//        return $this->getTeamRoleHelper()->convertToTeamRole( $game, $team, $externalTeamRole );
//    }

    public function getPerson(AgainstGame $againstGame, string|int $id): Person|null
    {
        return $this->personHelper->getPerson($againstGame, $id);
    }

//    public function convertToPerson(stdClass $externalPerson): ?Person
//    {
//        return $this->personHelper->convertToPerson($externalPerson);
//    }

    public function getImagePlayer(string $personExternalId): string
    {
        return $this->playerHelper->getImagePlayer($personExternalId);
    }

//    protected function showMetadata(
//        Competitions|CompetitionStructure|CompetitionDetails $externalSourceImpl,
//        int $entity
//    ): void {
//        if( $externalSourceImpl instanceof  CacheInfo ) {
//            $this->logger->info($externalSourceImpl->getCacheInfo($entity));
//        }
//        if ( $externalSourceImpl instanceof ApiHelper ) {
//            $this->logger->info("endpoint: " . $externalSourceImpl->getEndPoint($entity));
//        }
//    }

//
//    public function getEndPoint(int $dataTypeIdentifier = null): string
//    {
//        return $this->apiHelper->getEndPoint($dataTypeIdentifier);
//    }
//
//    public function getEndPointSuffix(int $dataTypeIdentifier): string
//    {
//        return $this->apiHelper->getEndPointSuffix($dataTypeIdentifier);
//    }

//    public function getCacheMinutes(int $dataTypeIdentifier): int
//    {
//        return $this->apiHelper->getCacheMinutes($dataTypeIdentifier);
//    }
//
//    public function getCacheId(int $dataTypeIdentifier): string
//    {
//        return $this->apiHelper->getCacheId($dataTypeIdentifier);
//    }
//
//    public function getCacheInfo(int $dataTypeIdentifier = null): string
//    {
//        return $this->apiHelper->getCacheInfo($dataTypeIdentifier);
//    }

    /**
     * @return array<string, string>|null
     */
    public function getProxy(): array|null
    {
        return $this->proxyOptions;
    }

    /**
     * @param array<string, string> $options
     */
    public function setProxy(array $options): void
    {
        $this->proxyOptions["username"] = $options["username"];
        $this->proxyOptions["password"] = $options["password"];
        $this->proxyOptions["host"] = $options["host"];
        $this->proxyOptions["port"] = $options["port"];
    }
}
