<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelpers;

use Psr\Log\LoggerInterface;
use SportsImport\CacheItemDb\CacheItemDbRepositoryInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\Data\PlayerProfile;
use stdClass;

final class PlayerApiHelper extends ApiHelperAbstract
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepositoryInterface $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    public function getProfile(string $externalId): PlayerProfile
    {
        $playerId = $this->getPlayerId($externalId);
        /** @var stdClass $json */
        $json = $this->getData(
            $this->getProfileEndPoint($playerId),
            $this->getProfileCacheId($playerId),
            $this->getProfileCacheMinutes()
        );
        if (!property_exists($json, 'player') || !($json->player instanceof stdClass)) {
            throw new \Exception('could not find stdClass-property "player"', E_ERROR);
        }
        if (!property_exists($json->player, 'team') || !($json->player->team instanceof stdClass)) {
            throw new \Exception('could not find stdClass-property "player.team"', E_ERROR);
        }

        $player = $this->jsonToDataConverter->convertPlayerJsonToData($json->player, null);
        $team = $this->jsonToDataConverter->convertTeamJsonToData($json->player->team);
        if ($player === null || $team === null) {
            throw new \Exception('could not convert SofaScore player profile', E_ERROR);
        }
        return new PlayerProfile($player, $team);
    }

    public function getProfileEndPoint(string $playerId): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getProfileCacheId($playerId);
    }

    public function getProfileCacheId(string $playerId): string
    {
        return 'player/' . $playerId;
    }

    public function getProfileCacheMinutes(): int
    {
        return 60;
    }

    public function getImage(string $externalId): string
    {
        return $this->getImgData($this->getImageEndPoint($externalId));
    }

    #[\Override]
    public function getCacheMinutes(): int
    {
        // case ExternalSource::DATA_PERSON_IMAGE:
        return 60 * 24 * 365;
    }

    public function getDefaultEndPoint(): string
    {
        return "player/**personId**/image";
    }

    public function getImageEndPoint(string $externalId): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getImageEndPointSuffix($externalId);
    }

    protected function getImageEndPointSuffix(string $externalId): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**personId**", $this->getPlayerId($externalId), $endpointSuffix);
    }

    private function getPlayerId(string $externalId): string
    {
        $slashPos = strpos($externalId, '/');
        return $slashPos === false ? $externalId : substr($externalId, $slashPos + 1);
    }
}
