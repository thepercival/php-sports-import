<?php

declare(strict_types=1);

namespace SportsImport\Repositories;

use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use SportsImport\CacheItemDb;

/**
 * @api
 * @template-extends EntityRepository<CacheItemDb>
 */
final class CacheItemDbRepository extends EntityRepository
{
    public function getItem(string $name): string|null
    {
        $cacheItem = $this->findOneBy(["name" => $name]);
        if ($cacheItem !== null &&
            ($cacheItem->getExpireDateTime() === null || $cacheItem->getExpireDateTime() > (new \DateTimeImmutable()))
        ) {
            /** @var string|resource $handle */
            $handle = $cacheItem->getValue();
            if (is_string($handle)) {
                return $handle;
            }
            $content = stream_get_contents($handle);
            rewind($handle);
            return $content === false ? null : $content;
        }
        return null;
    }

    public function getExpireDateTime(string $name): DateTimeImmutable|null
    {
        $cacheItem = $this->findOneBy(["name" => $name]);
        if ($cacheItem === null) {
            return null;
        }
        return $cacheItem->getExpireDateTime();
    }

    public function saveItem(string $name, mixed $value, int $nrOfMinutesToExpire = null): string
    {
        $cacheItem = $this->findOneBy(["name" => $name]);
        $expireDateTime = null;
        if ($nrOfMinutesToExpire !== null) {
            $expireDateTime = new \DateTimeImmutable();
            $expireDateTime = $expireDateTime->add(new \DateInterval('PT7M'));
        }
        if ($cacheItem === null) {
            $cacheItem = new CacheItemDb($name, $value, $expireDateTime);
        } else {
            $cacheItem->setValue($value);
            $cacheItem->setExpireDateTime($expireDateTime);
        }
        $this->getEntityManager()->persist($cacheItem);
        $this->getEntityManager()->flush();
        /** @var string $item */
        $item = $cacheItem->getValue();
        return $item;
    }

    public function removeItem(string $name): void
    {
        $cacheItem = $this->findOneBy(["name" => $name]);
        if ($cacheItem !== null) {
            $this->getEntityManager()->remove($cacheItem);
            $this->getEntityManager()->flush();
        }
    }
}
