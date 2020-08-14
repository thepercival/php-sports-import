<?php


namespace SportsImport\ExternalSource;


interface ApiHelper
{
    public function getEndPoint( int $dataTypeIdentifier = null ): string;
    public function getEndPointSuffix( int $dataTypeIdentifier ): string;
}