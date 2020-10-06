<?php

namespace SportsImport\ExternalSource;

use Sports\Competition as CompetitionBase;
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
use SportsImport\ExternalSource\Game as ExternalSourceGame;
use Sports\Game;

class SofaScore implements
    ExternalSourceImplementation,
    ExternalSourceSport,
    ExternalSourceAssociation,
    ExternalSourceSeason,
    ExternalSourceLeague,
    ExternalSourceCompetition,
    ExternalSourceTeam,
    ExternalSourceTeamCompetitor,
    ExternalSourceStructure,
    ExternalSourceGame,
    CacheInfo, ApiHelper, Proxy
{
    public const SPORTFILTER = "football";
    public const NAME = "SofaScore";

    /**
     * @var ExternalSourceBase
     */
    private $externalSource;
    /**
     * @var CacheItemDbRepository
     */
    private $cacheItemDbRepos;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var array|mixed[]
     */
    private $helpers;

    public function __construct(
        ExternalSourceBase $externalSource,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger = null
    ) {
        $this->logger = $logger;
        $this->helpers = [];
        $this->setExternalSource($externalSource);
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

    /**
     * @return ExternalSourceBase
     */
    public function getExternalSource()
    {
        return $this->externalSource;
    }

    /**
     * @param ExternalSourceBase $externalSource
     */
    public function setExternalSource(ExternalSourceBase $externalSource)
    {
        $this->externalSource = $externalSource;
    }

    /**
     * @return array|Sport[]
     */
    public function getSports(): array
    {
        return $this->getSportHelper()->getSports();
    }

    public function getSport($id = null): ?Sport
    {
        return $this->getSportHelper()->getSport($id);
    }

    protected function getSportHelper(): SofaScore\Helper\Sport
    {
        return $this->getHelper( SofaScore\Helper\Sport::class );
    }

    /**
     * @return array|Association[]
     */
    public function getAssociations(Sport $sport): array
    {
        return $this->getAssociationHelper()->getAssociations( $sport );
    }

    public function getAssociation(Sport $sport, $id = null): ?Association
    {
        return $this->getAssociationHelper()->getAssociation($sport, $id);
    }

    protected function getAssociationHelper(): SofaScore\Helper\Association
    {
        return $this->getHelper( SofaScore\Helper\Association::class );
    }

    /**
     * @return array|Season[]
     */
    public function getSeasons(): array
    {
        return $this->getSeasonHelper()->getSeasons();
    }

    public function getSeason($id = null): ?Season
    {
        return $this->getSeasonHelper()->getSeason($id);
    }

    protected function getSeasonHelper(): SofaScore\Helper\Season
    {
        return $this->getHelper( SofaScore\Helper\Season::class );
    }

    /**
     * @param Association $association
     * @return array|League[]
     */
    public function getLeagues(Association $association): array
    {
        return $this->getLeagueHelper()->getLeagues($association);
    }

    public function getLeague(Association $association, $id = null): ?League
    {
        return $this->getLeagueHelper()->getLeague($id);
    }

    protected function getLeagueHelper(): SofaScore\Helper\League
    {
        return $this->getHelper( SofaScore\Helper\League::class );
    }

    /**
     * @param Sport $sport
     * @param League $league
     * @return array|CompetitionBase[]
     */
    public function getCompetitions( Sport $sport, League $league ): array
    {
        return $this->getCompetitionHelper()->getCompetitions($sport, $league);
    }

    /**
     * @param Sport $sport
     * @param League $league
     * @param Season $season
     * @return CompetitionBase|null
     */
    public function getCompetition( Sport $sport, League $league, Season $season ): ?CompetitionBase
    {
        return $this->getCompetitionHelper()->getCompetition($sport, $league, $season);
    }

    protected function getCompetitionHelper(): SofaScore\Helper\Competition
    {
        return $this->getHelper( SofaScore\Helper\Competition::class );
    }

    /**
     * @return array|Team[]
     */
    public function getTeams(Competition $competition): array
    {
        return $this->getTeamHelper()->getTeams($competition);
    }

    public function getTeam(Competition $competition, $id): ?Team
    {
        return $this->getHelper( SofaScore\Helper\Team::class )->getTeam($competition, $id);
    }

    protected function getTeamHelper(): SofaScore\Helper\Team
    {
        return $this->getHelper( SofaScore\Helper\Team::class );
    }


    /**
     * @return array|TeamCompetitor[]
     */
    public function getTeamCompetitors(Competition $competition): array
    {
        return $this->getTeamCompetitorHelper()->getTeamCompetitors($competition);
    }

    public function getTeamCompetitor(Competition $competition, $id): ?TeamCompetitor
    {
        return $this->getTeamCompetitorHelper()->getTeamCompetitor($competition, $id);
    }

    protected function getTeamCompetitorHelper(): SofaScore\Helper\Competitor\Team
    {
        return $this->getHelper( SofaScore\Helper\Competitor\Team::class );
    }

    public function getStructure(Competition $competition): ?Structure
    {
        return $this->getStructureHelper()->getStructure($competition);
    }

    protected function getStructureHelper(): SofaScore\Helper\Structure
    {
        return $this->getHelper( SofaScore\Helper\Structure::class );
    }

    public function getBatchNrs(Competition $competition): array
    {
        return $this->getGameHelper()->getBatchNrs($competition);
    }

    public function getGames(Competition $competition, int $batchNr): array
    {
        return $this->getGameHelper()->getGames($competition, $batchNr);
    }

    public function getGame(Competition $competition, $id): ?Game
    {
        return $this->getGameHelper()->getGame($competition, $id);
    }

    protected function getGameHelper(): SofaScore\Helper\Game
    {
        return $this->getHelper( SofaScore\Helper\Game::class );
    }

    public function getEndPoint( int $dataTypeIdentifier = null ): string
    {
        return $this->getApiHelper()->getEndPoint( $dataTypeIdentifier );
    }

    public function getEndPointSuffix( int $dataTypeIdentifier ): string
    {
        return $this->getApiHelper()->getEndPointSuffix( $dataTypeIdentifier );
    }

    public function getCacheMinutes( int $dataTypeIdentifier ): int{
        return $this->getApiHelper()->getCacheMinutes( $dataTypeIdentifier );
    }

    public function getCacheId( int $dataTypeIdentifier ): string {
        return $this->getApiHelper()->getCacheId( $dataTypeIdentifier );
    }

    public function getCacheInfo( int $dataTypeIdentifier = null): string {
        return $this->getApiHelper()->getCacheInfo( $dataTypeIdentifier );
    }

    public function setProxy(array $options) {
        return $this->getApiHelper()->setProxy( $options );
    }

    /**
     * @return mixed
     */
    protected function getApiHelper()
    {
        if (array_key_exists(SofaScore\ApiHelper::class, $this->helpers)) {
            return $this->helpers[SofaScore\ApiHelper::class];
        }
        $this->helpers[SofaScore\ApiHelper::class] = new SofaScore\ApiHelper(
            $this->getExternalSource(),
            $this->cacheItemDbRepos
        );
        return $this->helpers[SofaScore\ApiHelper::class];
    }

    /**
     * @param string $helperClass
     * @return mixed
     */
    protected function getHelper( string $helperClass )
    {
        if (array_key_exists($helperClass, $this->helpers)) {
            return $this->helpers[$helperClass];
        }
        $this->helpers[$helperClass] = new $helperClass(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[$helperClass];
    }
}
