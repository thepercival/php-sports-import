<?php

namespace SportsImport;

class ExternalSource
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $website;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var string
     */
    private $apiurl;

    /**
     * @var string
     */
    private $apikey;
    /**
     * @var int
     */
    private $implementations;

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

    public function __construct($name, $website = null)
    {
        $this->setName($name);
        $this->setWebsite($website);
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        if (strlen($name) > static::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException("de naam mag maximaal ".static::MAX_LENGTH_NAME." karakters bevatten", E_ERROR);
        }
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * @param string $website
     */
    public function setWebsite($website = null)
    {
        if (strlen($website) > static::MAX_LENGTH_WEBSITE) {
            throw new \InvalidArgumentException("de omschrijving mag maximaal ".static::MAX_LENGTH_WEBSITE." karakters bevatten", E_ERROR);
        }
        $this->website = $website;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getApiurl()
    {
        return $this->apiurl;
    }

    /**
     * @param string $apiurl
     */
    public function setApiurl($apiurl)
    {
        $this->apiurl = $apiurl;
    }

    /**
     * @return string
     */
    public function getApikey()
    {
        return $this->apikey;
    }

    /**
     * @param string $apikey
     */
    public function setApikey($apikey)
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

    /**
     * @param int $implementations
     */
    public function setImplementations(int $implementations)
    {
        $this->implementations = $implementations;
    }
}
