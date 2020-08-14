<?php

namespace SportsImport\ExternalSource;

interface CacheInfo {
    public function getCacheMinutes( int $dataTypeIdentifier ): int;
    public function getCacheId( int $dataTypeIdentifier ): string;
    public function getCacheInfo( int $dataTypeIdentifier = null): string;
}