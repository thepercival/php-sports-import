<?php

namespace SportsImport\ExternalSource;

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
        if (array_key_exists(SofaScore\Helper\Sport::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Sport::class];
        }
        $this->helpers[SofaScore\Helper\Sport::class] = new SofaScore\Helper\Sport(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Sport::class];
    }

    /**
     * @return array|Association[]
     */
    public function getAssociations(): array
    {
        return $this->getAssociationHelper()->getAssociations();
    }

    public function getAssociation($id = null): ?Association
    {
        return $this->getAssociationHelper()->getAssociation($id);
    }

    protected function getAssociationHelper(): SofaScore\Helper\Association
    {
        if (array_key_exists(SofaScore\Helper\Association::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Association::class];
        }
        $this->helpers[SofaScore\Helper\Association::class] = new SofaScore\Helper\Association(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Association::class];
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
        if (array_key_exists(SofaScore\Helper\Season::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Season::class];
        }
        $this->helpers[SofaScore\Helper\Season::class] = new SofaScore\Helper\Season(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Season::class];
    }

    /**
     * @return array|League[]
     */
    public function getLeagues(): array
    {
        return $this->getLeagueHelper()->getLeagues();
    }

    public function getLeague($id = null): ?League
    {
        return $this->getLeagueHelper()->getLeague($id);
    }

    protected function getLeagueHelper(): SofaScore\Helper\League
    {
        if (array_key_exists(SofaScore\Helper\League::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\League::class];
        }
        $this->helpers[SofaScore\Helper\League::class] = new SofaScore\Helper\League(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\League::class];
    }

    /**
     * @return array|Competition[]
     */
    public function getCompetitions(): array
    {
        return $this->getCompetitionHelper()->getCompetitions();
    }

    public function getCompetition($id = null): ?Competition
    {
        return $this->getCompetitionHelper()->getCompetition($id);
    }

    protected function getCompetitionHelper(): SofaScore\Helper\Competition
    {
        if (array_key_exists(SofaScore\Helper\Competition::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Competition::class];
        }
        $this->helpers[SofaScore\Helper\Competition::class] = new SofaScore\Helper\Competition(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Competition::class];
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
        if (array_key_exists(SofaScore\Helper\Competitor\Team::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Competitor\Team::class];
        }
        $this->helpers[SofaScore\Helper\Competitor\Team::class] = new SofaScore\Helper\Competitor\Team(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Competitor\Team::class];
    }

    public function getStructure(Competition $competition): ?Structure
    {
        return $this->getStructureHelper()->getStructure($competition);
    }

    protected function getStructureHelper(): SofaScore\Helper\Structure
    {
        if (array_key_exists(SofaScore\Helper\Structure::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Structure::class];
        }
        $this->helpers[SofaScore\Helper\Structure::class] = new SofaScore\Helper\Structure(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Structure::class];
    }

    public function getBatchNrs(Competition $competition, bool $forImport): array
    {
        return $this->getGameHelper()->getBatchNrs($competition, $forImport);
    }

    public function getGames(Competition $competition, int $batchNr): array
    {
        return $this->getGameHelper()->getGames($competition, $batchNr);
    }

    protected function getGameHelper(): SofaScore\Helper\Game
    {
        if (array_key_exists(SofaScore\Helper\Game::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Game::class];
        }
        $this->helpers[SofaScore\Helper\Game::class] = new SofaScore\Helper\Game(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Game::class];
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
}
