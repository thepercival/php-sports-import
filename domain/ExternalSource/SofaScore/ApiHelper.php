<?php

namespace SportsImport\ExternalSource\SofaScore;

use DateTimeImmutable;
use Sports\League;
use Sports\Person;
use Sports\Team;
use SportsImport\Competitor as CompetitorBase;
use SportsImport\ExternalSource;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\CacheInfo;
use stdClass;
use GuzzleHttp\Client;
use SportsImport\Service as ImportService;
use SportsHelpers\Range;
use Sports\Competition;
use Sports\Competitor;
use Sports\Sport;
use Sports\Association;

class ApiHelper implements CacheInfo, ExternalSource\ApiHelper, ExternalSource\Proxy
{
    /**
     * @var ExternalSource
     */
    private $externalSource;
    /**
     * @var CacheItemDbRepository
     */
    private $cacheItemDbRepos;
    /**
     * @var Range|null
     */
    private $sleepRangeInSeconds;
    /**
     * @var Client
     */
    private $client;
    /**
     * @var array| null
     */
    private $proxyOptions;
    /**
     * @var array | Team[]
     */
    private $teamsCache = [];

    private const OLDAPIURL = "https://www.sofascore.com/";

    public function __construct(
        ExternalSource $externalSource,
        CacheItemDbRepository $cacheItemDbRepos
    ) {
        $this->cacheItemDbRepos = $cacheItemDbRepos;
        $this->externalSource = $externalSource;
    }

    protected function getClient()
    {
        if ($this->client === null) {
            $this->client = new Client();
        }
        return $this->client;
    }

    protected function getHeaders()
    {
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_SSL_VERIFYHOST => false,
            // CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 30
        ];
        if ($this->proxyOptions !== null) {
            $curlOptions[CURLOPT_PROXY] = $this->proxyOptions["username"] . ":" . $this->proxyOptions["password"]
                . "@" . $this->proxyOptions["host"] . ":" . $this->proxyOptions["port"];
        }
        return [
            'curl' => $curlOptions,
            'headers' => [/*"User:agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36"*/]
        ];
    }

    public function setProxy(array $options)
    {
        $this->proxyOptions = [];
        $this->proxyOptions["username"] = $options["username"];
        $this->proxyOptions["password"] = $options["password"];
        $this->proxyOptions["host"] = $options["host"];
        $this->proxyOptions["port"] = $options["port"];
    }

    protected function getDataFromCache(string $cacheId)
    {
        $data = $this->cacheItemDbRepos->getItem($cacheId);
        if ($data !== null) {
            return json_decode($data);
        }
        return null;
    }

    protected function getData(string $endpoint, string $cacheId, int $cacheMinutes)
    {
        $data = $this->getDataFromCache($cacheId);
        if ($data !== null) {
            return $data;
        }
        if ($this->sleepRangeInSeconds === null) {
            $this->sleepRangeInSeconds = new Range(6, 10);
        } else {
            sleep(rand($this->sleepRangeInSeconds->min, $this->sleepRangeInSeconds->max));
        }
//        return json_decode(
//            $this->cacheItemDbRepos->saveItem($cacheId, $this->getDataHelper($endpoint), $cacheMinutes)
//        );
        $response = $this->getClient()->get(
            $endpoint,
            $this->getHeaders()
        );
        return json_decode(
            $this->cacheItemDbRepos->saveItem($cacheId, $response->getBody()->getContents(), $cacheMinutes)
        );
    }

    public function getDateAsString(DateTimeImmutable $date): string
    {
        return $date->format("Y-m-d");
    }

    /**
     * @return array|stdClass[]
     */
    public function getSportsData(): array
    {
        $sports = $this->getData(
            $this->getEndPoint(ExternalSource::DATA_SPORTS),
            $this->getCacheId(ExternalSource::DATA_SPORTS),
            $this->getCacheMinutes(ExternalSource::DATA_SPORTS)
        );
        return (array)$sports;
    }

    /**
     * @return array|stdClass[]
     */
    public function getSeasonsData(): array
    {
        $seasons = [];
        $now = new \DateTimeImmutable();
        $thisYear2Digits = $now->format("Y");
        $nextYear2Digits = $now->modify("+1 years")->format("Y");
        $twoYears2Digits = $now->modify("+2 years")->format("Y");

        $thisSeasonName = $thisYear2Digits . "/" . $nextYear2Digits;
        $thisSeason = new stdClass();
        $thisSeason->name = $thisSeasonName;
        $seasons[] = $thisSeason;

        $nextSeasonName = $nextYear2Digits . "/" . $twoYears2Digits;
        $nextSeason = new stdClass();
        $nextSeason->name = $nextSeasonName;
        $seasons[] = $nextSeason;

        $thisYear4Digits = $now->format("Y");
        $nextYear4Digits = $now->modify("+1 years")->format("Y");

        $thisYear = new stdClass();
        $thisYear->name = $thisYear4Digits;
        $seasons[] = $thisYear;

        $nextYear = new stdClass();
        $nextYear->name = $nextYear4Digits;
        $seasons[] = $nextYear;

        return $seasons;
    }

    /**
     * @param Sport $sport
     * @return array|stdClass[]
     */
    public function getAssociationsData(Sport $sport): array
    {
        $dateApiData = $this->getData(
            $this->getAssociationsEndPoint($sport),
            $this->getAssociationsCacheId($sport),
            $this->getCacheMinutes(ExternalSource::DATA_ASSOCIATIONS)
        );
        if (property_exists($dateApiData, "categories") === false) {
            return [];
        }
        return $dateApiData->categories;
    }

    /**
     * @param Association $association
     * @return array|stdClass[]
     */
    public function getLeaguesData(Association $association): array
    {
        $dateApiData = $this->getData(
            $this->getLeaguesEndPoint($association),
            $this->getLeaguesCacheId($association),
            $this->getCacheMinutes(ExternalSource::DATA_LEAGUES)
        );
        if (property_exists($dateApiData, "groups") === false) {
            return [];
        }
        $leagues = [];
        foreach( $dateApiData->groups as $group ) {
            if (property_exists($group, "uniqueTournaments") === false) {
                continue;
            }
            $leagues = array_merge( $leagues, $group->uniqueTournaments );
        }
        return $leagues;
    }

    /**
     * @param League $league
     * @return array|stdClass[]
     */
    public function getCompetitionsData(League $league): array
    {
        $dateApiData = $this->getData(
            $this->getCompetitionsEndPoint($league),
            $this->getCompetitionsCacheId($league),
            $this->getCacheMinutes(ExternalSource::DATA_COMPETITIONS)
        );
        if (property_exists($dateApiData, "seasons") === false) {
            return [];
        }
        return $dateApiData->seasons;
    }

    /**
     * @return array|DateTimeImmutable[]
     */
    protected function getCompetitionDates(): array
    {
        $firstSaturday = $this->getFirstSaturdayInEvenWeek();
        return [
            $firstSaturday,
            $firstSaturday->modify("+28 days")
        ];
    }

    /**
     * @return array|DateTimeImmutable[]
     */
    protected function getCompetitionCacheDates(): array
    {
        $firstSaturday = $this->getFirstSaturdayInEvenWeek();
        return [
            $firstSaturday->modify("-28 days"),
            $firstSaturday->modify("+56 days")
        ];
    }

    protected function getFirstSaturdayInEvenWeek(): DateTimeImmutable
    {
        $today = (new DateTimeImmutable())->setTime(0, 0);

        $delta = 0;
        $weekNumber = (int)$today->format("W");
        if (($weekNumber % 2) === 1) {
            $delta = 7;
        }
        $dayCorrectWeek = $today->modify("+" . $delta . " days");

        $dayOfWeek = (int)$dayCorrectWeek->format("w");

        $deltaSaturDay = 6 - $dayOfWeek;

        $firstCorrectSaturday = $dayCorrectWeek->modify("+" . $deltaSaturDay . " days");

        return $firstCorrectSaturday;
    }

    public function getCompetitionId(stdClass $externalCompetition): ?int
    {
        if (!property_exists($externalCompetition, "season")) {
            return null;
        }
        if (!property_exists($externalCompetition->season, "id")) {
            return null;
        }
        return $externalCompetition->season->id;
    }

    public function getStructureData(Competition $competition): stdClass
    {
        return $this->getData(
            $this->getStructureEndPoint($competition),
            $this->getStructureCacheId($competition),
            $this->getCacheMinutes(ExternalSource::DATA_STRUCTURES)
        );
    }

    /**
     * @param Competition $competition
     * @param int $batchNr
     * @return array|stdClass[]
     */
    public function getBatchGameData(Competition $competition, int $batchNr): array
    {
        $gamesData = $this->getData(
            $this->getBatchGamesEndPoint($competition, $batchNr),
            $this->getBatchGamesCacheId($competition, $batchNr),
            $this->getCacheMinutes(ExternalSource::DATA_GAMES)
        );
        if (!property_exists($gamesData, "events")) {
            return [];
        }
        return $gamesData->events;
    }

    public function getGameData(Competition $competition, $gameId): stdClass
    {
        $gameData = $this->getData(
            $this->getGameEndPoint($competition, $gameId),
            $this->getGameCacheId($competition, $gameId),
            $this->getCacheMinutes(ExternalSource::DATA_GAME)
        );

        $gameData->lineups = $this->getData(
            $this->getGameLineupsEndPoint($gameId),
            $this->getGameLineupsCacheId($gameId),
            $this->getCacheMinutes(ExternalSource::DATA_GAME_LINEUPS )
        );

        $incidents = $this->getData(
            $this->getGameEventsEndPoint($gameId),
            $this->getGameEventsCacheId($gameId),
            $this->getCacheMinutes(ExternalSource::DATA_GAME_EVENTS )
        );
        if( property_exists( $incidents, "incidents") ) {
            $gameData->incidents  = $incidents->incidents;
        }
        return $gameData;
    }

    public function getCacheMinutes(int $dataTypeIdentifier): int
    {
        switch ($dataTypeIdentifier) {
            case ExternalSource::DATA_SPORTS:
                return 60 * 24 * 30 * 6;
            case ExternalSource::DATA_ASSOCIATIONS:
            case ExternalSource::DATA_COMPETITIONS:
                return 60 * 24 * 30;
            case ExternalSource::DATA_STRUCTURES:
            case ExternalSource::DATA_GAMES:
                return 60 * 24 * 7;
            case ExternalSource::DATA_GAME_LINEUPS:
            case ExternalSource::DATA_GAME:
                return 55;
            default:
                return 0;
        }
    }

    public function getCacheInfo(int $dataTypeIdentifier = null): string
    {
        if ($dataTypeIdentifier === null) {
            return "no report available yet";
        }
        $cacheMinutes = $this->getCacheMinutes($dataTypeIdentifier);
        $expireDateTime = $this->cacheItemDbRepos->getExpireDateTime($this->getCacheId($dataTypeIdentifier));
        if ($expireDateTime === null) {
            return "cachereport => cached: no, minutes-cached: " . $cacheMinutes;
        }
        $cachedDateTime = $expireDateTime->modify("- " . $this->getCacheMinutes($dataTypeIdentifier) . "minutes");

        $cachedAt = $cachedDateTime->format("'Y-m-d\TH:i:s\Z'");
        $expiredAt = $expireDateTime->format("'Y-m-d\TH:i:s\Z'");
        return "cachereport => cached:" . $cachedAt . ", minutes-cached: " . $cacheMinutes . ", expired: " . $expiredAt;
    }

    public function getCacheId(int $dataTypeIdentifier): string
    {
        return $this->getEndPointSuffix($dataTypeIdentifier);
    }

    public function getEndPoint(int $dataTypeIdentifier = null): string
    {
        $endpoint = $this->externalSource->getApiurl();
        if ($dataTypeIdentifier !== null) {
            $endpoint .= $this->getEndPointSuffix($dataTypeIdentifier) /*. $this->getUrlPostfix()*/;
        }
        return $endpoint;
    }

    /**
     *
     * {"seasons":[{"name":"Premier League 20\/21","year":"20\/21","id":29415},{"name":"Premier League 19\/20","year":"19\/20","id":23776},{"name":"Premier League 18\/19","year":"18\/19","id":17359},{"name":"Premier League 17\/18","year":"17\/18","id":13380},{"name":"Premier League 16\/17","year":"16\/17","id":11733},{"name":"Premier League 15\/16","year":"15\/16","id":10356},{"name":"Premier League 14\/15","year":"14\/15","id":8186},{"name":"Premier League 13\/14","year":"13\/14","id":6311},{"name":"Premier League 12\/13","year":"12\/13","id":4710},{"name":"Premier League 11\/12","year":"11\/12","id":3391},{"name":"Premier League 10\/11","year":"10\/11","id":2746},{"name":"Premier League 09\/10","year":"09\/10","id":2139},{"name":"Premier League 08\/09","year":"08\/09","id":1544},{"name":"Premier League 07\/08","year":"07\/08","id":581},{"name":"Premier League 06\/07","year":"06\/07","id":4},{"name":"Premier League 05\/06","year":"05\/06","id":3},{"name":"Premier League 04\/05","year":"04\/05","id":2},{"name":"Premier League 03\/04","year":"03\/04","id":1},{"name":"Premier League 02\/03","year":"02\/03","id":46},{"name":"Premier League 01\/02","year":"01\/02","id":47},{"name":"Premier League 00\/01","year":"00\/01","id":48},{"name":"Premier League 99\/00","year":"99\/00","id":49},{"name":"Premier League 98\/99","year":"98\/99","id":50},{"name":"Premier League 97\/98","year":"97\/98","id":51},{"name":"Premier League 96\/97","year":"96\/97","id":25682},{"name":"Premier League 95\/96","year":"95\/96","id":25681},{"name":"Premier League 94\/95","year":"94\/95","id":29167},{"name":"Premier League 93\/94","year":"93\/94","id":25680}]}
     *
     * @param int $dataTypeIdentifier
     * @return string
     * @throws \Exception
     */
    public function getEndPointSuffix(int $dataTypeIdentifier): string
    {
        if ($dataTypeIdentifier === ExternalSource::DATA_SPORTS) {
            return "sport/7200/event-count";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_ASSOCIATIONS) {
            return "sport/**sportId**/categories";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_LEAGUES) {
            return "category/**categoryId**/unique-tournaments";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_COMPETITIONS) {
            return "unique-tournament/**leagueId**/seasons";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_STRUCTURES) {
            return "u-tournament/**leagueId**/season/**competitionId**/json";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_GAMES) {
            return "unique-tournament/**leagueId**/season/**competitionId**/events/round/**batchNr**";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_GAME) {
            return "event/**gameId**";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_GAME_LINEUPS) {
            return "event/**gameId**/lineups";
        } elseif ($dataTypeIdentifier === ExternalSource::DATA_GAME_EVENTS) {
            return "event/**gameId**/incidents";
        }

        throw new \Exception("no endpointsuffix found for dataTypeIdentifier '". $dataTypeIdentifier ."'", E_ERROR );
    }

    protected function getAssociationsEndPoint(Sport $sport ): string
    {
        return $this->externalSource->getApiurl() . $this->getAssociationsEndPointSuffix(
                $sport
            );
    }

    protected function getAssociationsCacheId(Sport $sport ): string
    {
        return $this->getAssociationsEndPointSuffix($sport );
    }

    protected function getAssociationsEndPointSuffix(Sport $sport ): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_ASSOCIATIONS);
        return str_replace("**sportId**", $sport->getName(), $endpointSuffix);
    }

    protected function getLeaguesEndPoint(Association $association ): string
    {
        return $this->externalSource->getApiurl() . $this->getLeaguesEndPointSuffix($association);
    }

    protected function getLeaguesCacheId(Association $association): string
    {
        return $this->getLeaguesEndPointSuffix($association);
    }

    protected function getLeaguesEndPointSuffix(Association $association): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_LEAGUES);
        return str_replace("**categoryId**", $association->getId(), $endpointSuffix);
    }
    
    protected function getCompetitionsEndPoint(League $league): string
    {
        return $this->externalSource->getApiurl() . $this->getCompetitionsEndPointSuffix( $league );
    }

    protected function getCompetitionsCacheId(League $league): string
    {
        return $this->getCompetitionsEndPointSuffix($league);
    }

    protected function getCompetitionsEndPointSuffix(League $league): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_COMPETITIONS);
        return str_replace("**leagueId**", $league->getId(), $endpointSuffix);
    }

    protected function getStructureEndPoint(Competition $competition): string
    {
        return self::OLDAPIURL /*$this->externalSource->getApiurl()*/ . $this->getStructureEndPointSuffix(
                $competition
            );
    }

    protected function getStructureCacheId(Competition $competition): string
    {
        return $this->getStructureEndPointSuffix($competition);
    }

    protected function getStructureEndPointSuffix(Competition $competition): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_STRUCTURES);
        $retVal = str_replace("**leagueId**", $competition->getLeague()->getId(), $endpointSuffix);
        return str_replace("**competitionId**", $competition->getId(), $retVal);
    }

    protected function getBatchGamesEndPoint(Competition $competition, int $batchNr): string
    {
        return $this->externalSource->getApiurl() . $this->getBatchGamesEndPointSuffix(
                $competition,
                $batchNr
            );
    }

    protected function getBatchGamesCacheId(Competition $competition, int $batchNr): string
    {
        return $this->getBatchGamesEndPointSuffix($competition, $batchNr);
    }

    protected function getBatchGamesEndPointSuffix(Competition $competition, int $batchNr): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_GAMES);
        $retVal = str_replace("**leagueId**", $competition->getLeague()->getId(), $endpointSuffix);
        $retVal = str_replace("**competitionId**", $competition->getId(), $retVal);
        return str_replace("**batchNr**", (string)$batchNr, $retVal);
    }

    /**
     * @param Competition $competition
     * @param int|string $gameId
     * @return string
     */
    protected function getGameEndPoint(Competition $competition, $gameId): string
    {
        return $this->externalSource->getApiurl() . $this->getGameEndPointSuffix(
                $competition,
                $gameId
            );
    }

    /**
     * @param Competition $competition
     * @param int|string $gameId
     * @return string
     */
    protected function getGameCacheId(Competition $competition, $gameId): string
    {
        return $this->getGameEndPointSuffix($competition, $gameId);
    }

    /**
     * @param Competition $competition
     * @param int|string $gameId
     * @return string
     * @throws \Exception
     */
    protected function getGameEndPointSuffix(Competition $competition, $gameId): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_GAME);
        return str_replace("**gameId**", $gameId, $endpointSuffix);
    }

    /**
     * @param int|string $gameId
     * @return string
     */
    protected function getGameLineupsEndPoint($gameId): string
    {
        return $this->externalSource->getApiurl() . $this->getGameLineupsEndPointSuffix(
                $gameId
            );
    }

    /**
     * @param int|string $gameId
     * @return string
     */
    protected function getGameLineupsCacheId($gameId): string
    {
        return $this->getGameLineupsEndPointSuffix($gameId);
    }

    /**
     * @param int|string $gameId
     * @return string
     * @throws \Exception
     */
    protected function getGameLineupsEndPointSuffix($gameId): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_GAME_LINEUPS);
        return str_replace("**gameId**", $gameId, $endpointSuffix);
    }

    /**
     * @param int|string $gameId
     * @return string
     */
    protected function getGameEventsEndPoint($gameId): string
    {
        return $this->externalSource->getApiurl() . $this->getGameEventsEndPointSuffix(
                $gameId
            ) ;
    }

    /**
     * @param int|string $gameId
     * @return string
     */
    protected function getGameEventsCacheId($gameId): string
    {
        return $this->getGameEventsEndPointSuffix($gameId);
    }

    /**
     * @param int|string $gameId
     * @return string
     * @throws \Exception
     */
    protected function getGameEventsEndPointSuffix($gameId): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_GAME_EVENTS);
        return str_replace("**gameId**", $gameId, $endpointSuffix);
    }


    /**
     * {
     *   "name": "FC Smolevichi",
     *   "slug": "fc-smolevichi",
     *   "gender": "M",
     *   "disabled": false,
     *   "national": false,
     *   "id": 42964,
     *   "subTeams": []
     * }
     */
    public function convertTeam(Association $association, stdClass $externalTeam ): Team {
        if( array_key_exists( $externalTeam->id, $this->teamsCache ) ) {
            return $this->teamsCache[$externalTeam->id];
        }
        $team = new Team($association, $externalTeam->name);
        $team->setId($externalTeam->id);
        $abbreviation = $externalTeam->name;
        $startPos = 0;
        if( strpos( strtolower($abbreviation) , "fc ") !== false ) {
            $startPos = 3;
        }

        $team->setAbbreviation(strtoupper(substr($abbreviation, $startPos, Team::MAX_LENGTH_ABBREVIATION)));
        $team->setImageUrl("https://www.sofascore.com/images/team-logo/football_".$team->getId().".png");
        $this->teamsCache[$team->getId()] = $team;
        return $team;
    }

    /**
     * G, D, M, F
     */
    public function convertLine(string $line ): int {
        if( $line === "G" ) {
            return Team::LINE_KEEPER;
        } elseif( $line === "D" ) {
            return Team::LINE_DEFENSE;
        } elseif( $line === "M" ) {
            return Team::LINE_MIDFIELD;
        } else if( $line === "F" ) {
            return Team::LINE_FORWARD;
        }
        return 0;
    }

    public function convertToSeasonId( string $name ): string {
        $strposSlash = strpos($name, "/");
        if ( $strposSlash === false || $strposSlash === 4 ) {
            return $name;
        }
        $newName = substr($name, 0, $strposSlash ) . "/" . "20" . substr($name, $strposSlash + 1);
        if( $strposSlash === 2 ) {
            $newName = "20" . $newName;
        }
        return $newName;
    }
}
