<?php

namespace SportsImport\ExternalSource;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource;

class Factory implements Proxy
{
    /**
     * @var array<string, string>
     */
    protected array $proxyOptions = [];

    protected const COMPETITIONS = 1;
    protected const COMPETITION_STRUCTURE = 2;
    protected const GAMES_AND_PLAYERIMAGES = 4;

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

    protected function getImplementations(Implementation $externalSourceImplementation): int
    {
        $implementations = 0;
        if ($externalSourceImplementation instanceof ExternalSource\Competitions) {
            $implementations += self::COMPETITIONS;
        }
        if ($externalSourceImplementation instanceof ExternalSource\CompetitionStructure) {
            $implementations += self::COMPETITION_STRUCTURE;
        }
        if ($externalSourceImplementation instanceof ExternalSource\GamesAndPlayers) {
            $implementations += self::GAMES_AND_PLAYERIMAGES;
        }
        return $implementations;
    }
}
