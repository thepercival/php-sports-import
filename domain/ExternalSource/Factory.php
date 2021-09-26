<?php

namespace SportsImport\ExternalSource;

use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource;
use Psr\Log\LoggerInterface;

class Factory implements Proxy
{
    /**
     * @var array<string, string>
     */
    protected array $proxyOptions = [];

    protected const SPORT = 1;
    protected const ASSOCIATION = 2;
    protected const SEASON = 4;
    protected const LEAGUE = 8;
    protected const COMPETITION = 16;
    protected const TEAMCOMPETITOR = 32;

    public function __construct(
        protected Repository $externalSourceRepos,
        protected CacheItemDbRepository $cacheItemDbRepos,
        protected LoggerInterface $logger
    ) {
    }

    public function setLogger(LoggerInterface $logger): void
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
     * @param array<string, string> $options
     */
    public function setProxy(array $options): void
    {
        $this->proxyOptions = $options;
    }

    public function createByName(string $name): ?Implementation
    {
        $externalSource = $this->externalSourceRepos->findOneBy(["name" => $name ]);
        if ($externalSource === null) {
            return null;
        }
        $implementation = $this->create($externalSource);
        if ($implementation === null) {
            return null;
        }
        if (count($this->proxyOptions) > 0 && ($implementation instanceof Proxy)) {
            $implementation->setProxy($this->proxyOptions);
        }
        return $implementation;
    }

    protected function create(ExternalSource $externalSource): Implementation|null
    {
        if ($externalSource->getName() === SofaScore::NAME) {
            return new SofaScore($externalSource, $this->cacheItemDbRepos, $this->logger);
        }
        return null;
    }

    /**
     * @param list<ExternalSource> $externalSources
     */
    public function setImplementations(array $externalSources): void
    {
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

    protected function getImplementations(ExternalSource\Implementation $implementation): int
    {
        $implementations = 0;
        if ($implementation instanceof ExternalSource\Sport) {
            $implementations += self::SPORT;
        }
        if ($implementation instanceof ExternalSource\Association) {
            $implementations += self::ASSOCIATION;
        }
        if ($implementation instanceof ExternalSource\Season) {
            $implementations += self::SEASON;
        }
        if ($implementation instanceof ExternalSource\League) {
            $implementations += self::LEAGUE;
        }
        if ($implementation instanceof ExternalSource\Competition) {
            $implementations += self::COMPETITION;
        }
        if ($implementation instanceof ExternalSource\Competitor\Team) {
            $implementations += self::TEAMCOMPETITOR;
        }
        return $implementations;
    }
}
