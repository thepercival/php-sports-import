<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Sports\Sport\FootballLine;
use SportsImport\CacheItemDb\Repository as CacheItemDbRepository;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;
use stdClass;

class Player extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    public function getImage(string $externalId): string
    {
        return $this->getImgData($this->getImageEndPoint($externalId));
    }

    public function getCacheMinutes(): int
    {
        // case ExternalSource::DATA_PERSON_IMAGE:
        return 60 * 24 * 365;
    }

    public function getDefaultEndPoint(): string
    {
        return "images/player/image_**personId**.png";
    }

    public function getImageEndPoint(string $externalId): string
    {
        return self::IMAGEBASEURL . $this->getImageEndPointSuffix($externalId);
        // return $this->sofaScore->getExternalSource()->getApiurl() . $this->getImageEndPointSuffix($externalId);
    }

    protected function getImageEndPointSuffix(string $externalId): string
    {
        $slashPos = strpos($externalId, "/");
        if ($slashPos !== false) {
            $personImageId = substr($externalId, $slashPos + 1);
        } else {
            $personImageId = $externalId;
        }
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**personId**", $personImageId, $endpointSuffix);
    }

    /**
     * {
     *      "name":"Justin Bijlow",
     *      "slug":"bijlow-justin",
     *      "shortName":"J. Bijlow",
     *      "position":"G",
     *      "userCount":209,
     *      "id":556696,
     *      "marketValueCurrency":"\u20ac",
     *      "dateOfBirthTimestamp":885427200
     * }
     * @throws \Exception
     */
    public function convertApiDataRow(stdClass $apiDataRow, stdClass|null $apiDataStatistics): PlayerData|null
    {
        if (!property_exists($apiDataRow, 'id')) {
            $this->logger->error('could not find stdClass-property "id"');
            // throw new \Exception('could not find stdClass-property "id"', E_ERROR);
            return null;
        }
        if (!property_exists($apiDataRow, 'slug')) {
            $this->logger->error('could not find stdClass-property "slug"');
            // throw new \Exception('could not find stdClass-property "slug"', E_ERROR);
            return null;
        }
        if (!property_exists($apiDataRow, 'position')) {
            // throw new \Exception('could not find stdClass-property "position"', E_ERROR);
            $this->logger->error('could not find stdClass-property "position"');
            return null;
        }

        $externalId = (string)$apiDataRow->slug . "/" . (string)$apiDataRow->id;

        $dateOfBirth = null;
        if (property_exists($apiDataRow, "dateOfBirthTimestamp")) {
            $dateOfBirthTimestamp = (string)$apiDataRow->dateOfBirthTimestamp;
            $dateOfBirth = new DateTimeImmutable("@" . $dateOfBirthTimestamp);
        }

        $line = $this->convertLine((string)$apiDataRow->position);

        $playerData = new PlayerData($externalId, (string)$apiDataRow->name, $line, $dateOfBirth);
        if ($apiDataStatistics !== null) {
            if (property_exists($apiDataStatistics, "minutesPlayed")) {
                /** @var string|int $minutesPlayed */
                $minutesPlayed = $apiDataStatistics->minutesPlayed;
                if ($minutesPlayed > 0) {
                    $playerData->nrOfMinutesPlayed = (int)$minutesPlayed;
                }
            }
        }
        return $playerData;
    }

    /**
     * G, D, M, F
     */
    public function convertLine(string $line): FootballLine
    {
        if ($line === "G") {
            return FootballLine::GoalKeeper;
        } elseif ($line === "D") {
            return FootballLine::Defense;
        } elseif ($line === "M") {
            return FootballLine::Midfield;
        } elseif ($line === "F") {
            return FootballLine::Forward;
        }
        throw new \Exception('unknown line: "' . $line . '"');
    }
}
