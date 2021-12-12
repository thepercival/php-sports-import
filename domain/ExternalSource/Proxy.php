<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

interface Proxy
{
    /**
     * @param array<string, string> $options
     */
    public function setProxy(array $options): void;
}
