<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Psr\Log\LoggerInterface;
use Sports\Association;
use Sports\Competition;
use Sports\Competition as CompetitionBase;
use Sports\Competitor\Team as TeamCompetitor;
use Sports\Game\Against as AgainstGame;
use Sports\League;
use Sports\Person;
use Sports\Season;
use Sports\Sport;
use Sports\Structure;
use Sports\Team;
use SportsImport\ExternalSource;
use SportsImport\ExternalSource as ExternalSourceBase;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGame as AgainstGameApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameEvents as AgainstGameEventsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGameLineups as AgainstGameLineupsApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\AgainstGames as AgainstGamesApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Association as AssociationApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Competition as CompetitionApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Competitor\Team as TeamCompetitorApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\GameRoundNumbers as GameRoundNumbersApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\JsonToDataConverter;
use SportsImport\ExternalSource\SofaScore\ApiHelper\League as LeagueApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Player as PlayerApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Season as SeasonApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Sport as SportApiHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Team as TeamApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Association as AssociationHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Competition as CompetitionHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Competitor\Team as TeamCompetitorHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Game\Against as AgainstGameHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Game\RoundNumbers as GameRoundNumbersHelper;
use SportsImport\ExternalSource\SofaScore\Helper\League as LeagueHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Person as PersonHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Season as SeasonHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Sport as SportHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Structure as StructureHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Team as TeamHelper;
use SportsImport\ExternalSource\SofaScore\Helper\Transfer as TransferHelper;
use SportsImport\Repositories\CacheItemDbRepository as CacheItemDbRepository;
use SportsImport\Transfer;

final class SofaScore implements
    Implementation,
    Competitions,
    CompetitionStructure,
    GamesAndPlayers,
    Transfers,
    Proxy
{
    public const string NAME = 'SofaScore';
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
    protected TransferHelper $transferHelper;
    protected SofaScore\Helper\Structure $structureHelper;
    protected SofaScore\Helper\Team $teamHelper;
    protected SofaScore\Helper\Competitor\Team $teamCompetitorHelper;
    protected SofaScore\Helper\Game\Against $againstGameHelper;
    protected SofaScore\Helper\Player $playerHelper;
    // protected SofaScore\Helper\TeamCompetitorAttacher\Role $teamRoleHelper;

    // protected TransfersApiHelper $transfersApiHelper;

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

        $playerApiHelper = new PlayerApiHelper($this, $cacheItemDbRepos, $logger);
        $this->personHelper = new PersonHelper($playerApiHelper, $this, $this->logger);
        $this->playerHelper = new SofaScore\Helper\Player($playerApiHelper, $this, $this->logger);

        $teamApiHelper = new TeamApiHelper($this, $cacheItemDbRepos, $logger);
        $this->teamHelper = new TeamHelper($this->personHelper, $teamApiHelper, $this, $this->logger);

        $this->transferHelper = new TransferHelper(
            $this->personHelper,
            $this->teamHelper,
            $teamApiHelper,
            $this,
            $this->logger
        );

        $teamCompetitorApiHelper = new TeamCompetitorApiHelper($this, $cacheItemDbRepos, $logger);
        $this->teamCompetitorHelper = new TeamCompetitorHelper(
            $this->teamHelper,
            $teamCompetitorApiHelper,
            $this,
            $this->logger
        );

        $this->structureHelper = new StructureHelper($teamCompetitorApiHelper, $this, $this->logger);



        $againstGameLineupsApiHelper = new AgainstGameLineupsApiHelper($playerApiHelper, $this, $cacheItemDbRepos, $logger);
        $againstGameEventsApiHelper = new AgainstGameEventsApiHelper($playerApiHelper, $this, $cacheItemDbRepos, $logger);
        $againstGameApiHelper = new AgainstGameApiHelper(
            $againstGameLineupsApiHelper,
            $againstGameEventsApiHelper,
            $this,
            $cacheItemDbRepos,
            $logger
        );
        $againstGamesApiHelper = new AgainstGamesApiHelper($againstGameApiHelper, $this, $cacheItemDbRepos, $logger);


        $this->againstGameHelper = new AgainstGameHelper(
            $this->teamHelper,
            $this->personHelper,
            $againstGamesApiHelper,
            $againstGameApiHelper,
            $againstGameLineupsApiHelper,
            $againstGameEventsApiHelper,
            new JsonToDataConverter($this->logger),
            $this,
            $this->logger
        );

        $gameRoundNumbersApiHelper = new GameRoundNumbersApiHelper($this, $cacheItemDbRepos, $logger);
        $this->gameRoundsHelper = new GameRoundNumbersHelper(
            $gameRoundNumbersApiHelper,
            $this,
            $this->logger
        );

        // $this->transfersApiHelper = new TransfersApiHelper

//        $teamRoleApiHelper = new TeamRoleApiHelper($externalSource, $cacheItemDbRepos, $logger);
//        $this->teamRoleHelper = new TeamRoleHelper($teamRoleApiHelper, $this, $this->logger);


        $this->cacheItemDbRepos = $cacheItemDbRepos;
        /* $this->structureOptions = new StructureOptions(
             new VoetbalRange(1, 32),
             new VoetbalRange( 2, 256),
             new VoetbalRange( 2, 30)
         );*/
    }

    #[\Override]
    public function getExternalSource(): ExternalSource
    {
        return $this->externalSource;
    }

    /**
     * @return array<int|string, Sport>
     */
    #[\Override]
    public function getSports(): array
    {
        return $this->sportHelper->getSports();
    }

    #[\Override]
    public function getSport(string|int $id): Sport|null
    {
        return $this->sportHelper->getSport($id);
    }

    /**
     * @return array<int|string, Association>
     */
    #[\Override]
    public function getAssociations(Sport $sport): array
    {
        return $this->associationHelper->getAssociations($sport);
    }

    #[\Override]
    public function getAssociation(Sport $sport, string|int $id): Association|null
    {
        return $this->associationHelper->getAssociation($sport, $id);
    }

    /**
     * @return array<int|string, Season>
     */
    #[\Override]
    public function getSeasons(): array
    {
        return $this->seasonHelper->getSeasons();
    }

    #[\Override]
    public function getSeason(string|int $id): ?Season
    {
        return $this->seasonHelper->getSeason($id);
    }

    /**
     * @param Association $association
     * @return array<int|string, League>
     */
    #[\Override]
    public function getLeagues(Association $association): array
    {
        return $this->leagueHelper->getLeagues($association);
    }

    #[\Override]
    public function getLeague(Association $association, string|int $id): League|null
    {
        return $this->leagueHelper->getLeague($association, $id);
    }

    /**
     * @param Sport $sport
     * @param League $league
     * @return array<int|string, CompetitionBase>
     */
    #[\Override]
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
    #[\Override]
    public function getCompetition(Sport $sport, League $league, Season $season): ?CompetitionBase
    {
        return $this->competitionHelper->getCompetition($sport, $league, $season);
    }

    /**
     * @return list<Team>
     */
    #[\Override]
    public function getTeams(Competition $competition): array
    {
        return $this->teamHelper->getTeams($competition);
    }

    #[\Override]
    public function getTeam(Competition $competition, string|int $id): Team|null
    {
        return $this->teamHelper->getTeam($competition, $id);
    }

    #[\Override]
    public function getImageTeam(string $teamExternalId): string
    {
        return $this->teamHelper->getImageTeam($teamExternalId);
    }

    /**
     * @return list<TeamCompetitor>
     */
    #[\Override]
    public function getTeamCompetitors(Competition $competition): array
    {
        return $this->teamCompetitorHelper->getTeamCompetitors($competition);
    }

    #[\Override]
    public function getTeamCompetitor(Competition $competition, string|int $id): ?TeamCompetitor
    {
        return $this->teamCompetitorHelper->getTeamCompetitor($competition, $id);
    }

    #[\Override]
    public function getStructure(Competition $competition): Structure
    {
        return $this->structureHelper->getStructure($competition);
    }

    /**
     * @param Competition $competition
     * @return list<int>
     */
    #[\Override]
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
    #[\Override]
    public function getAgainstGamesBasics(Competition $competition, int $gameRoundNumber): array
    {
        return $this->againstGameHelper->getAgainstGameBasics($competition, $gameRoundNumber);
    }

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @param bool $resetCache
     * @return array<int|string, AgainstGame>
     * @throws \Exception
     */
    #[\Override]
    public function getAgainstGamesComplete(Competition $competition, int $gameRoundNumber, bool $resetCache): array
    {
        return $this->againstGameHelper->getAgainstGamesComplete($competition, $gameRoundNumber, $resetCache);
    }

    #[\Override]
    public function getAgainstGame(Competition $competition, string|int $id, bool $resetCache): AgainstGame|null
    {
        return $this->againstGameHelper->getAgainstGame($competition, $id, $resetCache);
    }

//    public function convertToTeamRole( Game $game, TeamCompetitorAttacher $team, stdClass $externalTeamRole): TeamRole {
//        return $this->getTeamRoleHelper()->convertToTeamRole( $game, $team, $externalTeamRole );
//    }

    public function getPerson(string|int $id): Person|null
    {
        return $this->personHelper->getPerson($id);
    }

//    /**
//     * @param CompetitionBase $competition
//     * @return list<TransferData>
//     */
//    public function getTransfers(CompetitionAttacher $competition): array
//    {
//        return $this->tra->getPerson($againstGame, $id);
//    }

//    public function convertToPerson(stdClass $externalPerson): ?Person
//    {
//        return $this->personHelper->convertToPerson($externalPerson);
//    }

    #[\Override]
    public function getImagePlayer(string $personExternalId): string
    {
        return $this->playerHelper->getImagePlayer($personExternalId);
    }

    /**
     * @param Competition $competition
     * @param Team $team
     * @return list<Transfer>
     */
    #[\Override]
    public function getTransfers(Competition $competition, Team $team): array
    {
        return $this->transferHelper->getTransfers($team);
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
    #[\Override]
    public function setProxy(array $options): void
    {
        $this->proxyOptions["username"] = $options["username"];
        $this->proxyOptions["password"] = $options["password"];
        $this->proxyOptions["host"] = $options["host"];
        $this->proxyOptions["port"] = $options["port"];
    }
}
