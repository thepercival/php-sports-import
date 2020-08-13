<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 4-3-18
 * Time: 19:47
 */

namespace Voetbal\ExternalSource;

use Voetbal\ExternalSource as ExternalSourceBase;
use Voetbal\ExternalSource\Implementation as ExternalSourceImplementation;
use Voetbal\CacheItemDb\Repository as CacheItemDbRepository;
use Voetbal\Structure\Range as StructureOptions;
use Psr\Log\LoggerInterface;

use Voetbal\Sport;
use Voetbal\ExternalSource\Sport as ExternalSourceSport;
use Voetbal\ExternalSource\Association as ExternalSourceAssociation;
use Voetbal\Association;
use Voetbal\ExternalSource\Season as ExternalSourceSeason;
use Voetbal\Season;
use Voetbal\ExternalSource\League as ExternalSourceLeague;
use Voetbal\League;
use Voetbal\ExternalSource\Competition as ExternalSourceCompetition;
use Voetbal\Competition;
use Voetbal\ExternalSource\Competitor as ExternalSourceCompetitor;
use Voetbal\Competitor;
use Voetbal\ExternalSource\Structure as ExternalSourceStructure;
use Voetbal\Structure;
use Voetbal\ExternalSource\Game as ExternalSourceGame;
use Voetbal\Game;

class SofaScore implements
    ExternalSourceImplementation,
    ExternalSourceSport,
    ExternalSourceAssociation,
                           ExternalSourceSeason,
    ExternalSourceLeague,
    ExternalSourceCompetition,
                           ExternalSourceCompetitor,
    ExternalSourceStructure,
    ExternalSourceGame
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
     * @var array
     */
    private $helpers;
    /**
     * @var StructureOptions
     */
    // protected $structureOptions;

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
     * @return array|Competitor[]
     */
    public function getCompetitors(Competition $competition): array
    {
        return $this->getCompetitorHelper()->getCompetitors($competition);
    }

    public function getCompetitor(Competition $competition, $id): ?Competitor
    {
        return $this->getCompetitorHelper()->getCompetitor($competition, $id);
    }

    protected function getCompetitorHelper(): SofaScore\Helper\Competitor
    {
        if (array_key_exists(SofaScore\Helper\Competitor::class, $this->helpers)) {
            return $this->helpers[SofaScore\Helper\Competitor::class];
        }
        $this->helpers[SofaScore\Helper\Competitor::class] = new SofaScore\Helper\Competitor(
            $this,
            $this->getApiHelper(),
            $this->logger
        );
        return $this->helpers[SofaScore\Helper\Competitor::class];
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
}
