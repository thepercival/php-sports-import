<?php

namespace SportsImport\ExternalSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource;
use SportsImport\Repositories\CacheItemDbRepository as CacheItemDbRepository;

/**
 * @api
 */
final class Factory implements Proxy
{
    /**
     * @var array<string, string>
     */
    protected array $proxyOptions = [];

    /** @var EntityRepository<ExternalSource>  */
    protected EntityRepository $externalSourceRepos;

    protected const int COMPETITIONS = 1;
    protected const int COMPETITION_STRUCTURE = 2;
    protected const int GAMES_AND_PLAYERIMAGES = 4;

    public function __construct(
        EntityManagerInterface $entityManager,
        protected CacheItemDbRepository    $cacheItemDbRepos,
        protected LoggerInterface          $logger
    ) {
        $metaData = $entityManager->getClassMetadata(ExternalSource::class);
        $this->externalSourceRepos = new EntityRepository($entityManager, $metaData);
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
    #[\Override]
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
