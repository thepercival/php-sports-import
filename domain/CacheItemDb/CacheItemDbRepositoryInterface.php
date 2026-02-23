<?php

namespace SportsImport\CacheItemDb;

use DateTimeImmutable;

interface CacheItemDbRepositoryInterface
{
    public function getItem(string $name): string|null;
    public function getExpireDateTime(string $name): DateTimeImmutable|null;
    public function saveItem(string $name, mixed $value, int|null $nrOfMinutesToExpire = null): string;
    public function removeItem(string $name): void;
}