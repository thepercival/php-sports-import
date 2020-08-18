<?php

namespace SportsImport\ExternalSource;

interface Proxy {
    /**
     * @param array|string[] $options
     */
    public function setProxy( array $options );
}