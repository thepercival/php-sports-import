<?php

namespace SportsImport\ExternalSource;

use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource;
use Psr\Log\LoggerInterface;

class Factory implements Proxy
{
    /**
     * @var Repository
     */
    protected $externalSourceRepos;
    /**
     * @var CacheItemDbRepository
     */
    protected $cacheItemDbRepos;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var array|string[]|null
     */
    protected $proxyOptions;

    protected const SPORT = 1;
    protected const ASSOCIATION = 2;
    protected const SEASON = 4;
    protected const LEAGUE = 8;
    protected const COMPETITION = 16;
    protected const TEAMCOMPETITOR = 32;

    public function __construct(
        Repository $externalSourceRepos,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        $this->externalSourceRepos = $externalSourceRepos;
        $this->cacheItemDbRepos = $cacheItemDbRepos;
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

//    public function create(ExternalSource $externalSource)
//    {
//        if ($externalSource->getName() === "SofaScore") {
//            return new SofaScore($externalSource, $this->cacheItemDbRepos, $this->logger/*,$this->settings*/);
//        }
//        return null;
//    }

    /**
     * @param array|string[] $options
     */
    public function setProxy(array $options) {
        $this->proxyOptions = $options;
    }

    public function createByName(string $name): ?Implementation
    {
        $externalSource = $this->externalSourceRepos->findOneBy(["name" => $name ]);
        if ($externalSource === null) {
            return null;
        }
        $implementation = $this->create($externalSource);
        if( $implementation === null ) {
            return null;
        }
        if( is_array($this->proxyOptions) && ($implementation instanceof Proxy) ) {
            $implementation->setProxy( $this->proxyOptions );
        }
        return $implementation;
    }

    protected function create(ExternalSource $externalSource): ?Implementation
    {
        if ($externalSource->getName() === SofaScore::NAME) {
            return new SofaScore($externalSource, $this->cacheItemDbRepos, $this->logger);
        }
        return null;
    }

    /**
     * @param array|ExternalSource[] $externalSources
     */
    public function setImplementations(array $externalSources)
    {
        /** @var ExternalSource $externalSource */
        foreach ($externalSources as $externalSource) {
            $externalSourceImpl = $this->create($externalSource);
            if ($externalSourceImpl === null) {
                continue;
            }
            $externalSourceImpl->getExternalSource()->setImplementations(
                $this->getImplementations($externalSourceImpl)
            );
        }
    }

    protected function getImplementations(ExternalSource\Implementation $implementation)
    {
        $implementations = 0;
        if ($implementation instanceof ExternalSource\Sport) {
            $implementations += static::SPORT;
        }
        if ($implementation instanceof ExternalSource\Association) {
            $implementations += static::ASSOCIATION;
        }
        if ($implementation instanceof ExternalSource\Season) {
            $implementations += static::SEASON;
        }
        if ($implementation instanceof ExternalSource\League) {
            $implementations += static::LEAGUE;
        }
        if ($implementation instanceof ExternalSource\Competition) {
            $implementations += static::COMPETITION;
        }
        if ($implementation instanceof ExternalSource\Competitor\Team) {
            $implementations += static::TEAMCOMPETITOR;
        }
        return $implementations;
    }
}
