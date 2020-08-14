<?php

namespace SportsImport\ExternalSource\SofaScore;

use DateTimeImmutable;
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

class ApiHelper implements CacheInfo, ExternalSource\ApiHelper
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
     * @var Client
     */
    private $client;
    /**
     * @var Range|null
     */
    private $sleepRangeInSeconds;

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
        return [
            'curl' => [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 5
            ],
            'headers' => []
        ];
    }

    protected function getData(string $endpoint, string $cacheId, int $cacheMinutes)
    {
        $data = $this->cacheItemDbRepos->getItem( $cacheId );
        if ($data !== null) {
            return json_decode($data);
        }

        if ($this->sleepRangeInSeconds === null) {
            $this->sleepRangeInSeconds = new Range(5, 60);
        } else {
            sleep(rand($this->sleepRangeInSeconds->min, $this->sleepRangeInSeconds->max));
        }

        $response = $this->getClient()->get(
            $endpoint,
            $this->getHeaders()
        );
        return json_decode(
            $this->cacheItemDbRepos->saveItem($cacheId, $response->getBody()->getContents(), $cacheMinutes)
        );
    }

    protected function getUrlPostfix()
    {
        return "?_=" . (new \DateTimeImmutable())->getTimestamp();
    }

    public function getCurrentDateAsString(): string
    {
        return $this->getDateAsString((new DateTimeImmutable())->modify("+15 days") );
    }

    public function getDateAsString( DateTimeImmutable $date ): string
    {
        return $date->format("Y-m-d");
    }

    public function getSportsData(): stdClass
    {
        return $this->getData(
            $this->getEndPoint( ExternalSource::DATA_SPORTS ),
            $this->getCacheId( ExternalSource::DATA_SPORTS ),
            $this->getCacheMinutes( ExternalSource::DATA_SPORTS )
        );
    }

    /**
     * @param Sport $sport
     * @param array|DateTimeImmutable[] $dates = null
     * @return stdClass
     */
    public function getCompetitionsData(Sport $sport, array $dates = null ): stdClass
    {
        if ($dates === null) {
            $dates = $this->getDefaultDates();
        }
        $datesData = new stdClass();
        foreach( $dates as $date ) {
            $dateApiData = $this->getData(
                $this->getCompetitionsEndPoint($sport,$date),
                $this->getCompetitionsCacheId( $sport,$date ),
                $this->getCacheMinutes( ExternalSource::DATA_COMPETITIONS )
            );

            if( property_exists($datesData, "sportItem") === false ) {
                $datesData->sportItem = $dateApiData->sportItem;
            } else {
                $datesData->sportItem->tournaments = array_merge( $datesData->sportItem->tournaments, $dateApiData->sportItem->tournaments );
            }
        }
        return $datesData;
    }

    /**
     * @return array|DateTimeImmutable[]
     */
    protected function getDefaultDates(): array {
        $today = (new DateTimeImmutable())->setTime(0, 0);
        return [
            $today,
            $today->modify("+5 days")
        ];
    }

    public function getStructureData(Competition $competition): stdClass
    {
        return $this->getData(
            $this->getStructureEndPoint($competition),
            $this->getStructureCacheId( $competition ),
            $this->getCacheMinutes( ExternalSource::DATA_STRUCTURES )
        );
    }

    public function getBatchGameData(Competition $competition, int $batchNr): stdClass
    {
        return $this->getData(
            $this->getBatchGamesEndPoint($competition, $batchNr),
            $this->getBatchGamesCacheId( $competition, $batchNr ),
            $this->getCacheMinutes( ExternalSource::DATA_GAMES )
        );
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
    public function convertTeam(Association $association, stdClass $externalTeam): Team
    {
        $team = new Team($association, $externalTeam->name);
        // @TODO DEPRECATED
//        $team->setId($externalTeam->id);
//        $team->setAbbreviation(substr($externalTeam->name, 0, Team::MAX_LENGTH_ABBREVIATION));
//        $team->setImageUrl("https://www.sofascore.com/images/team-logo/football_".$competitor->getId().".png");
        return $team;
    }

    public function getCacheMinutes( int $dataTypeIdentifier ): int {

        if( $dataTypeIdentifier === ExternalSource::DATA_SPORTS ) {
            return 60 * 24 * 7;
        } else if( $dataTypeIdentifier === ExternalSource::DATA_COMPETITIONS ) {
            return 60 * 24;
        } else if( $dataTypeIdentifier === ExternalSource::DATA_STRUCTURES ) {
            return 60 * 24 * 7;
        }

//        public const ASSOCIATION_CACHE_MINUTES = 1440 * 7; // 60 * 24
//        public const SEASON_CACHE_MINUTES = 1440 * 7; // 60 * 24
//        public const LEAGUE_CACHE_MINUTES = 1440 * 7; // 60 * 24
//        public const COMPETITION_CACHE_MINUTES = 1440 * 7; // 60 * 24
//        public const COMPETITOR_CACHE_MINUTES = 1440 * 7; // 60 * 24
//        public const GAME_CACHE_MINUTES = 10; // 60 * 24
        return 0;
    }

    public function getCacheInfo( int $dataTypeIdentifier = null): string
    {
        if( $dataTypeIdentifier === null ) {
            return "no report available yet";
        }
        $cacheMinutes = $this->getCacheMinutes( $dataTypeIdentifier );
        $expireDateTime = $this->cacheItemDbRepos->getExpireDateTime( $this->getCacheId( $dataTypeIdentifier ) );
        if( $expireDateTime === null ) {
            return "cachereport => cached: no, minutes-cached: " . $cacheMinutes;
        }
        $cachedDateTime = $expireDateTime->modify("- " . $this->getCacheMinutes( $dataTypeIdentifier ) . "minutes" );

        $cachedAt = $cachedDateTime->format("'Y-m-d\TH:i:s\Z'");
        $expiredAt = $expireDateTime->format("'Y-m-d\TH:i:s\Z'");
        return "cachereport => cached:" . $cachedAt .", minutes-cached: " . $cacheMinutes . ", expired: " . $expiredAt;
    }

    public function getCacheId( int $dataTypeIdentifier ): string
    {
        return $this->getEndPointSuffix($dataTypeIdentifier);
    }

    public function getEndPoint( int $dataTypeIdentifier = null ): string
    {
        $endpoint = $this->externalSource->getApiurl();
        if( $dataTypeIdentifier !== null ) {
            $endpoint .= $this->getEndPointSuffix( $dataTypeIdentifier ) . $this->getUrlPostfix();
        }
        return $endpoint;
    }

    public function getEndPointSuffix( int $dataTypeIdentifier ): string
    {
        if( $dataTypeIdentifier === ExternalSource::DATA_SPORTS ) {
            return "event/count/by-sports/json";
        } else if( $dataTypeIdentifier === ExternalSource::DATA_COMPETITIONS ) {
            return "**sportId**//**date**/json";
        } else if ( $dataTypeIdentifier === ExternalSource::DATA_STRUCTURES ) {
            return "u-tournament/**leagueId**/season/**competitionId**/json";
        } else if ( $dataTypeIdentifier === ExternalSource::DATA_GAMES ) {
            return "u-tournament/**leagueId**/season/**competitionId**/matches/round/**batchNr**";
        }

        throw new \Exception("unknown endpointsuffix");
    }

    protected function getCompetitionsEndPoint( Sport $sport, \DateTimeImmutable $dateTime ): string
    {
        return $this->externalSource->getApiurl() . $this->getCompetitionsEndPointSuffix($sport, $dateTime );
    }

    protected function getCompetitionsCacheId( Sport $sport, \DateTimeImmutable $dateTime ): string
    {
        return $this->getCompetitionsEndPointSuffix($sport, $dateTime );
    }

    protected function getCompetitionsEndPointSuffix( Sport $sport, \DateTimeImmutable $dateTime ): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_COMPETITIONS);
        $retVal = str_replace("**sportId**", $sport->getName(), $endpointSuffix);
        return str_replace("**date**", $this->getDateAsString($dateTime), $retVal);
    }

    protected function getStructureEndPoint( Competition $competition ): string
    {
        return $this->externalSource->getApiurl() . $this->getStructureEndPointSuffix($competition );
    }

    protected function getStructureCacheId( Competition $competition ): string
    {
        return $this->getStructureEndPointSuffix($competition );
    }

    protected function getStructureEndPointSuffix( Competition $competition ): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_STRUCTURES);
        $retVal = str_replace("**leagueId**", $competition->getLeague()->getId(), $endpointSuffix);
        return str_replace("**competitionId**", $competition->getId(), $retVal);
    }

    protected function getBatchGamesEndPoint( Competition $competition, int $batchNr ): string
    {
        return $this->externalSource->getApiurl() . $this->getBatchGamesEndPointSuffix($competition, $batchNr );
    }

    protected function getBatchGamesCacheId( Competition $competition, int $batchNr ): string
    {
        return $this->getBatchGamesEndPointSuffix($competition, $batchNr );
    }

    protected function getBatchGamesEndPointSuffix( Competition $competition, int $batchNr ): string
    {
        $endpointSuffix = $this->getEndPointSuffix(ExternalSource::DATA_GAMES);
        $retVal = str_replace("**leagueId**", $competition->getLeague()->getId(), $endpointSuffix);
        $retVal = str_replace("**competitionId**", $competition->getId(), $retVal);
        return str_replace("**batchNr**", (string)$batchNr, $retVal);
    }

    /*
        public function getLeague(ExternalLeague $externalLeague): ?\stdClass
        {
            $leagues = $this->getData("competitions/?plan=TIER_ONE")->competitions;
            $foundLeagues = array_filter(
                $leagues,
                function ($league) use ($externalLeague) {
                    return $league->id === (int)$externalLeague->getExternalId();
                }
            );
            if (count($foundLeagues) !== 1) {
                return null;
            }
            return reset($foundLeagues);
        }

        public function getCompetition(ExternalLeague $externalLeague, ExternalSeason $externalSeason): ?\stdClass
        {
            $externalSystemLeague = $this->getLeague($externalLeague);
            if ($externalSystemLeague === null) {
                return null;
            }

            $externalSystemLeagueDetails = $this->getData("competitions/" . $externalSystemLeague->id);

            $leagueSeaons = $externalSystemLeagueDetails->seasons;
            $foundLeagueSeaons = array_filter(
                $leagueSeaons,
                function ($leagueSeaon) use ($externalSeason) {
                    return substr($leagueSeaon->startDate, 0, 4) === $externalSeason->getExternalId();
                }
            );
            if (count($foundLeagueSeaons) !== 1) {
                return null;
            }
            return reset($foundLeagueSeaons);
        }

        public function getRounds(ExternalLeague $externalLeague, ExternalSeason $externalSeason): ?array
        {
            $matches = $this->getGames($externalLeague, $externalSeason);
            if ($this->getRoundsHelperAllMatchesHaveDate($matches) !== true) {
                return [];
            }
            uasort(
                $matches,
                function ($matchA, $matchB) {
                    return $matchA->utcDate < $matchB->utcDate;
                }
            );

            $rounds = [];
            foreach ($matches as $match) {
                if (array_key_exists($match->stage, $rounds) === false) {
                    $round = new \stdClass();
                    $round->name = $match->stage;
                    $round->poules = $this->getRoundsHelperGetPoules($matches, $match->stage);
                    $rounds[$match->stage] = $round;
                }
            }
            return $rounds;
        }

        protected function getRoundsHelperAllMatchesHaveDate(array $matches): bool
        {
            foreach ($matches as $match) {
                if (strlen($match->utcDate) === 0) {
                    return false;
                }
            }
            return true;
        }


        protected function getRoundsHelperGetPoules(array $matches, string $stage): array
        {
            $stageMatches = array_filter(
                $matches,
                function ($match) use ($stage) {
                    return $match->stage === $stage;
                }
            );
            $poules = $this->getRoundsHelperGetPoulesHelper($stageMatches);
            if (count($poules) === 0) {
                throw new \Exception("no places to be found for stage " . $stage, E_ERROR);
            }
            return $poules;
        }

        protected function getRoundsHelperGetPoulesHelper(array $stageMatches): array
        {
            $movePlaces = function (&$poules, $oldPoule, $newPoule) {
                $newPoule->places = array_merge($oldPoule->places, $newPoule->places);
                unset($poules[array_search($oldPoule, $poules)]);
            };

            $poules = [];
            foreach ($stageMatches as $stageMatch) {
                $homeCompetitorId = $stageMatch->homeTeam->id;
                $awayCompetitorId = $stageMatch->awayTeam->id;
                if ($homeCompetitorId === null || $awayCompetitorId === null) {
                    continue;
                }
                $homePoule = $this->getRoundsHelperGetPoule($poules, $homeCompetitorId);
                $awayPoule = $this->getRoundsHelperGetPoule($poules, $awayCompetitorId);
                if ($homePoule === null && $awayPoule === null) {
                    $poule = new \stdClass();
                    $poule->places = [$homeCompetitorId, $awayCompetitorId];
                    $poule->games = [];
                    $poules[] = $poule;
                } elseif ($homePoule !== null && $awayPoule === null) {
                    $homePoule->places[] = $awayCompetitorId;
                } elseif ($homePoule === null && $awayPoule !== null) {
                    $awayPoule->places[] = $homeCompetitorId;
                } elseif ($homePoule !== $awayPoule) {
                    $movePlaces($poules, $awayPoule, $homePoule);
                }
            }
            $this->getRoundsHelperGetPoulesHelperExt($poules, $stageMatches);
            return $poules;
        }

        protected function getRoundsHelperGetPoulesHelperExt(array &$poules, array $stageMatches)
        {
            foreach ($stageMatches as $stageMatch) {
                $homeCompetitorId = $stageMatch->homeTeam->id;
                $awayCompetitorId = $stageMatch->awayTeam->id;
                if ($homeCompetitorId === null || $awayCompetitorId === null) {
                    continue;
                }
                $homePoule = $this->getRoundsHelperGetPoule($poules, $homeCompetitorId);
                $awayPoule = $this->getRoundsHelperGetPoule($poules, $awayCompetitorId);
                if ($homePoule === null || $homePoule !== $awayPoule) {
                    continue;
                }
                $homePoule->games[] = $stageMatch;
            }

            foreach ($poules as $poule) {
                $nrOfPlaces = count($poule->places);
                $nrOfGames = count($poule->games);

                $nrOfGamesPerGameRound = ($nrOfPlaces - ($nrOfPlaces % 2)) / 2;
                $nrOfGameRounds = ($nrOfGames / $nrOfGamesPerGameRound);
                $poule->nrOfHeadtohead = $nrOfGameRounds / ($nrOfPlaces - 1);
            }
        }

        protected function getRoundsHelperGetPoule($poules, $competitorId): ?\stdClass
        {
            foreach ($poules as $poule) {
                if (array_search($competitorId, $poule->places) !== false) {
                    return $poule;
                }
            }
            return null;
        }

        public function getCompetitors(ExternalLeague $externalLeague, ExternalSeason $externalSeason): ?array
        {
            $retVal = $this->getData(
                "competitions/" . $externalLeague->getExternalId() . "/teams?season=" . $externalSeason->getExternalId()
            );
            return $retVal->teams;
        }

        public function getGames(
            ExternalLeague $externalLeague,
            ExternalSeason $externalSeason,
            string $stage = null // round
        ): ?array
        {
            $retVal = $this->getData(
                "competitions/" . $externalLeague->getExternalId() . "/matches?season=" . $externalSeason->getExternalId()
            );
            if ($stage === null) {
                return $retVal->matches;
            }
            return array_filter(
                $retVal->matches,
                function ($match) use ($stage) {
                    return $match->stage === $stage;
                }
            );
        }

        public function getGame(
            ExternalLeague $externalLeague,
            ExternalSeason $externalSeason,
            string $stage = null
            // round
            ,
            int $externalSystemGameId
        ): ?stdClass {
            $games = $this->getGames($externalLeague, $externalSeason, $stage);
            $filteredGames = array_filter(
                $games,
                function ($game) use ($externalSystemGameId) {
                    return $game->id === $externalSystemGameId;
                }
            );
            return reset($filteredGames);
        }

        public function getDate(string $date)
        {
            if (strlen($date) === 0) {
                return null;
            }
            return \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $date);
        }*/
}
