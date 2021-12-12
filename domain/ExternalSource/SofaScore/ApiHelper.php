<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore;

use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Sports\State;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SportsHelpers\SportRange;

abstract class ApiHelper
{
    private const NrOfRetries = 2;
    private SportRange|null $sleepRangeInSeconds = null;
    private Client|null $client = null;

    public const IMAGEBASEURL = "https://www.sofascore.com/";

    public function __construct(
        protected SofaScore $sofaScore,
        protected CacheItemDbRepository $cacheItemDbRepos,
        protected LoggerInterface $logger
    ) {
    }

    protected function getClient(): Client
    {
        if ($this->client === null) {
            $this->client = new Client();
        }
        return $this->client;
    }

    protected function getHeaders(): array
    {
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_SSL_VERIFYPEER => false,
            // CURLOPT_SSL_VERIFYHOST => false,
            // CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 30
        ];
        $proxyOptions = $this->sofaScore->getProxy();
        if ($proxyOptions !== null) {
            $curlOptions[CURLOPT_PROXY] = $proxyOptions["username"] . ":" . $proxyOptions["password"]
                . "@" . $proxyOptions["host"] . ":" . $proxyOptions["port"];
        }
        return [
            'curl' => $curlOptions,
            'headers' => [/*"User:agent" => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36"*/]
        ];
    }

    protected function removeDataFromCache(string $cacheId): void
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
            throw new Exception("could not get sofascore-data after retries: cacheid => " . $cacheId, E_ERROR);
        }
        $content = $response->getBody()->getContents();
        $retVal = $this->cacheItemDbRepos->saveItem($cacheId, $content, $cacheMinutes);
        $this->logger->info("received data size: " . $this->formatBytes(mb_strlen($retVal)));
        return json_decode($retVal);
    }

    protected function formatBytes(false|int $bytes, int $precision = 2): string
    {
        if ($bytes === false) {
            return 'unknown size';
        }
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = (int)min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
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
        $cachedDateTime = $expireDateTime->modify("- " . $this->getCacheMinutes() . "minutes");

        $cachedAt = $cachedDateTime->format("'Y-m-d\TH:i:s\Z'");
        $expiredAt = $expireDateTime->format("'Y-m-d\TH:i:s\Z'");
        return "cachereport => cached:" . $cachedAt . ", minutes-cached: " . $cacheMinutes . ", expired: " . $expiredAt;
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

    public function convertState(int $state): int
    {
        if ($state === 0) { // not started
            return State::Created;
        } elseif ($state === 60) { // postponed
            return State::Canceled;
        } elseif ($state === 70) { // canceled
            return State::Canceled;
        } elseif ($state === 100) { // finished
            return State::Finished;
        }
        throw new \Exception("unknown sofascore-status: " . $state, E_ERROR);
    }
}
