<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Sports\Game\State as GameState;
use SportsHelpers\Dev\ByteFormatter;
use SportsHelpers\SportRange;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\JsonToDataConverter;

abstract class ApiHelper
{
    private const NrOfRetries = 2;
    private SportRange|null $sleepRangeInSeconds = null;
    private Client|null $client = null;
    protected JsonToDataConverter $jsonToDataConverter;

    public function __construct(
        protected SofaScore $sofaScore,
        protected CacheItemDbRepository $cacheItemDbRepos,
        protected LoggerInterface $logger
    ) {
        $this->jsonToDataConverter = new JsonToDataConverter($logger);
    }

    protected function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client();
        }
        return $this->client;
    }

    /**
     * @return array<string|int, mixed>
     */
    protected function getHeaders(): array
    {
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_SSL_VERIFYHOST => false,
            // CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 6
        ];
        $proxyOptions = $this->sofaScore->getProxy();
        if ($proxyOptions !== null) {
            $curlOptions[CURLOPT_PROXY] = $proxyOptions["username"] . ":" . $proxyOptions["password"]
                . "@" . $proxyOptions["host"] . ":" . $proxyOptions["port"];
        }

//        :authority:        api.sofascore.com
//:method:HEAD
//:path:/api/v1/event/11388466/odds/1/all
//:scheme:https
//Accept:*/*
//    Accept-Encoding: gzip, deflate, br
//Accept-Language: nl,en;q=0.9,en-GB;q=0.8,en-US;q=0.7
//Origin: https://www.sofascore.com
//Referer: https://www.sofascore.com/
//Sec-Ch-Ua: "Chromium";v="122", "Not(A:Brand";v="24", "Microsoft Edge";v="122"
//Sec-Ch-Ua-Mobile: ?0
//Sec-Ch-Ua-Platform: "Linux"
//Sec-Fetch-Dest: empty
//Sec-Fetch-Mode: cors
//Sec-Fetch-Site: same-site
//User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 Edg/122.0.0.0

        return [
            'curl' => $curlOptions,
            'headers' => [/*"User:agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36"*/]
        ];
    }

    protected function resetDataFromCache(string $cacheId): void
    {
        $this->cacheItemDbRepos->removeItem($cacheId);
    }

    protected function getDataFromCache(string $cacheId): mixed
    {
        $data = $this->cacheItemDbRepos->getItem($cacheId);
        if ($data !== null) {
            return json_decode($data);
        }
        return null;
    }

    protected function getData(string $endpoint, string $cacheId, int $cacheMinutes, int $nrOfRetries = 0): mixed
    {
        $data = $this->getDataFromCache($cacheId);
        if ($data !== null) {
            $this->logger->info('got data from cache(default minutes = ' . $cacheMinutes . ') with cacheid: "' . $cacheId . '"');
            return $data;
        }
        if ($this->sleepRangeInSeconds === null) {
            $this->sleepRangeInSeconds = new SportRange(3, 5);
        } else {
            /** @var positive-int $randomNr */
            $randomNr = rand($this->sleepRangeInSeconds->getMin(), $this->sleepRangeInSeconds->getMax());
            sleep($randomNr);
        }
//        return json_decode(
//            $this->cacheItemDbRepos->saveItem($cacheId, $this->getDataHelper($endpoint), $cacheMinutes)
//        );
        $proxyDescription = ($this->sofaScore->getProxy() === null ? 'no ' : '') . 'proxy used';
        $this->logger->info('request for "' . $endpoint . '" ('.$proxyDescription.') try ' . ($nrOfRetries+1));

        $client = $this->getClient();
        try {
            $response = $client->get(
                $endpoint,
                $this->getHeaders()
            );
        } catch (RequestException $e) {
            if ($nrOfRetries < self::NrOfRetries) {
                return $this->getData($endpoint, $cacheId, $cacheMinutes, $nrOfRetries + 1);
            }
            throw new Exception("req: could not get sofascore-data after retries: cacheid => " . $cacheId, E_ERROR);
        } catch (Exception $e) {
            if ($nrOfRetries < self::NrOfRetries) {
                return $this->getData($endpoint, $cacheId, $cacheMinutes, $nrOfRetries + 1);
            }
            throw new Exception("could not get sofascore-data after retries: cacheid => " . $cacheId, E_ERROR);
        }
        $content = $response->getBody()->getContents();
        $retVal = $this->cacheItemDbRepos->saveItem($cacheId, $content, $cacheMinutes);
        $this->logger->info("received data size: " . (new ByteFormatter(mb_strlen($retVal))));
        return json_decode($retVal);
    }

    protected function getImgData(string $endpoint): string
    {
        if ($this->sleepRangeInSeconds === null) {
            $this->sleepRangeInSeconds = new SportRange(3, 5);
        } else {
            /** @var positive-int $randomNr */
            $randomNr = rand($this->sleepRangeInSeconds->getMin(), $this->sleepRangeInSeconds->getMax());
            sleep($randomNr);
        }
//        return json_decode(
//            $this->cacheItemDbRepos->saveItem($cacheId, $this->getDataHelper($endpoint), $cacheMinutes)
//        );
        $this->logger->info($endpoint);
        $client = $this->getClient();
        $response = $client->get(
            $endpoint,
            $this->getHeaders()
        );
        if ($response->getStatusCode() !== 200) {
            return '';
        }
        return $response->getBody()->getContents();
    }

    public function getDateAsString(DateTimeImmutable $date): string
    {
        return $date->format("Y-m-d");
    }

    // abstract public function getDefaultEndPoint(): string;



//    /**
//     * @param Competition $competition
//     * @return list<PlaceData>
//     * @throws Exception
//     */
//    public function getStructureData(Competition $competition): array
//    {
//        $teamCompetitorsData = $this->getTeamCompetitorsData($competition);
//
//    }

//    public function getPersonImageData(string $personExternalId): string
//    {
//        $imgData = $this->getImgData(
//            $this->getPersonImageEndPoint($personExternalId)
//        );
//        return $imgData;
//    }
//


    abstract public function getCacheMinutes(): int;

    public function getCacheInfoHelper(string $cacheId): string
    {
        $cacheMinutes = $this->getCacheMinutes();
        $expireDateTime = $this->cacheItemDbRepos->getExpireDateTime($cacheId);
        if ($expireDateTime === null) {
            return "cachereport => cached: no, minutes-cached: " . $cacheMinutes;
        }
        $cachedDateTime = $expireDateTime->sub(new \DateInterval('PT' . $this->getCacheMinutes() . 'M'));

        $cachedAt = $cachedDateTime->format(DateTimeInterface::ISO8601);
        $expiredAt = $expireDateTime->format(DateTimeInterface::ISO8601);
        return 'cachereport => cached:' . $cachedAt . ', minutes-cached: ' . $cacheMinutes . ', expired: ' . $expiredAt;
    }

    public function convertToSeasonId(string $name): string
    {
        $strposSlash = strpos($name, "/");
        if ($strposSlash === false || $strposSlash === 4) {
            return $name;
        }
        $newName = substr($name, 0, $strposSlash) . "/" . "20" . substr($name, $strposSlash + 1);
        if ($strposSlash === 2) {
            $newName = "20" . $newName;
        }
        return $newName;
    }

    public function convertState(int $state): GameState
    {
        if ($state === 0) { // not started
            return GameState::Created;
        } elseif ($state === 60) { // postponed
            return GameState::Canceled;
        } elseif ($state === 70) { // canceled
            return GameState::Canceled;
        } elseif ($state === 90) { // abandoned
            return GameState::Canceled;
        }
        elseif ($state === 100) { // finished
            return GameState::Finished;
        }
        throw new \Exception("unknown sofascore-status: " . $state, E_ERROR);
    }
}
