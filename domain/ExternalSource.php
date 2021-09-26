<?php
declare(strict_types=1);

namespace SportsImport;

use SportsHelpers\Identifiable;

class ExternalSource extends Identifiable
{
    private string|null $username = null;
    private string|null $password = null;
    private string|null $apikey = null;
    private int|null $implementations = null;

    const MAX_LENGTH_NAME = 50;
    const MAX_LENGTH_WEBSITE = 255;
    const MAX_LENGTH_USERNAME = 50;
    const MAX_LENGTH_PASSWORD = 50;
    const MAX_LENGTH_APIURL = 255;
    const MAX_LENGTH_APIKEY = 255;

    const DATA_SPORTS = 1;
    const DATA_ASSOCIATIONS = 2;
    const DATA_SEASONS = 4;
    const DATA_LEAGUES = 8;
    const DATA_COMPETITIONS = 16;
    const DATA_TEAMS = 32;
    const DATA_TEAMCOMPETITORS = 64;
    // const DATA_PERSONCOMPETITORS = 2;
    const DATA_STRUCTURES = 256;
    const DATA_GAMES = 512;
    const DATA_GAME = 1024;
    const DATA_GAME_LINEUPS = 2048;
    const DATA_GAME_EVENTS = 4096;
    const DATA_PERSON_IMAGE = 8192;
    const DATA_TEAM_IMAGE = 16384;

    public function __construct(
        private string $name,
        private string $apiurl,
        private string|null $website = null
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        if (strlen($name) > self::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException("de naam mag maximaal ".self::MAX_LENGTH_NAME." karakters bevatten", E_ERROR);
        }
        $this->name = $name;
    }

    public function getWebsite(): string|null
    {
        return $this->website;
    }

    public function setWebsite(string|null $website): void
    {
        if (isset($website) && strlen($website) > self::MAX_LENGTH_WEBSITE) {
            throw new \InvalidArgumentException("de omschrijving mag maximaal ".self::MAX_LENGTH_WEBSITE." karakters bevatten", E_ERROR);
        }
        $this->website = $website;
    }

    public function getUsername(): string|null
    {
        return $this->username;
    }

    public function setUsername(string|null $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string|null
    {
        return $this->password;
    }

    public function setPassword(string|null $password): void
    {
        $this->password = $password;
    }

    public function getApiurl(): string
    {
        return $this->apiurl;
    }

    public function setApiurl(string $apiurl): void
    {
        $this->apiurl = $apiurl;
    }

    public function getApikey(): string|null
    {
        return $this->apikey;
    }

    public function setApikey(string|null $apikey): void
    {
        $this->apikey = $apikey;
    }

    public function getImplementations(): int
    {
        if ($this->implementations === null) {
            $this->implementations = 0;
        }
        return $this->implementations;
    }

    public function setImplementations(int $implementations): void
    {
        $this->implementations = $implementations;
    }
}
