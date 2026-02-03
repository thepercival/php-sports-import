<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Sports\Sport\FootballLine;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;
use SportsImport\ExternalSource\SofaScore\Data\Team as TeamData;
use SportsImport\ExternalSource\SofaScore\Data\Transfer as TransferData;
use stdClass;

final class JsonToDataConverter
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * @param stdClass $transferJson
     * @return TransferData|null
     * @throws \Exception
     */
    public function convertTransferJsonToData(stdClass $transferJson): TransferData|null
    {
        if (!property_exists($transferJson, 'player')) {
            $this->logger->warning('could not find stdClass-property "player"');
            return null;
        }
        /** @var stdClass $player */
        $player = $transferJson->player;
        $playerData = $this->convertPlayerJsonToData($player, null);
        if ($playerData === null) {
            $this->logger->warning('no player data found for transfer');
            return null;
        }

        if (!property_exists($transferJson, 'transferDateTimestamp')) {
            $this->logger->warning('could not find stdClass-property "transferDateTimestamp"');
            return null;
        }
        /** @var int $transferDateTimestamp */
        $transferDateTimestamp = $transferJson->transferDateTimestamp;

        if (!property_exists($transferJson, 'transferFrom')) {
            $this->logger->warning('could not find stdClass-property "transferFrom"');
            return null;
        }

        /** @var stdClass $transferFrom */
        $transferFrom = $transferJson->transferFrom;
        $teamFrom = $this->convertTeamJsonToData($transferFrom);
        if ($teamFrom === null) {
            $this->logger->warning('no from-team-data found for transfer');
            return null;
        }

        if (!property_exists($transferJson, 'transferTo')) {
            $this->logger->warning('could not find stdClass-property "transferTo"');
            return null;
        }
        /** @var stdClass $transferTo */
        $transferTo = $transferJson->transferTo;
        $teamTo = $this->convertTeamJsonToData($transferTo);
        if ($teamTo === null) {
            $this->logger->warning('no to-team-data found for transfer');
            return null;
        }
        return new TransferData(
            $playerData,
            new DateTimeImmutable("@" . $transferDateTimestamp),
            $teamFrom,
            $teamTo
        );
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
     *      "proposedMarketValueRaw": { "value": 1100000, "currency": "EUR" }
     *      "dateOfBirthTimestamp":885427200
     * }
     * @throws \Exception
     */
    public function convertPlayerJsonToData(stdClass $apiDataRow, stdClass|null $apiDataStatistics): PlayerData|null
    {
        if (!property_exists($apiDataRow, 'id')) {
            $this->logger->warning('could not find stdClass-property "id"');
            // throw new \Exception('could not find stdClass-property "id"', E_ERROR);
            return null;
        }
        if (!property_exists($apiDataRow, 'slug')) {
            $this->logger->warning('could not find stdClass-property ('.(string)$apiDataRow->id.') "slug"');
            // throw new \Exception('could not find stdClass-property "slug"', E_ERROR);
            return null;
        }
        // addMissingPositionToPlayer
        if (!property_exists($apiDataRow, 'position') ) {
            if( $apiDataRow->slug === 'caner-demircioglu') {
                $apiDataRow->position = 'F'; // G D M F
            } else if( $apiDataRow->slug === 'coen-dunnink') {
                $apiDataRow->position = 'M'; // G D M F
            }
        }

        if (!property_exists($apiDataRow, 'position')) {
            // throw new \Exception('could not find stdClass-property "position"', E_ERROR);
            $this->logger->warning('could not find stdClass-property ('.(string)$apiDataRow->slug.') "position"');
            return null;
        }

        $externalId = (string)$apiDataRow->slug . "/" . (string)$apiDataRow->id;

        $dateOfBirth = null;
        if (property_exists($apiDataRow, "dateOfBirthTimestamp")) {
            $dateOfBirthTimestamp = (string)$apiDataRow->dateOfBirthTimestamp;
            $dateOfBirth = new DateTimeImmutable("@" . $dateOfBirthTimestamp);
        }

        $marketValue = 0;
        if (property_exists($apiDataRow, "proposedMarketValueRaw")) {
            $proposedMarketValueRaw = (object)$apiDataRow->proposedMarketValueRaw;
            if (property_exists($proposedMarketValueRaw, "value")) {
                $marketValue = (int)$proposedMarketValueRaw->value;
            }
        }

        $line = $this->convertLine((string)$apiDataRow->position);

        $playerData = new PlayerData($externalId, (string)$apiDataRow->name, $line, $dateOfBirth, $marketValue);
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

    public function convertTeamJsonToData(stdClass $teamJson): TeamData|null
    {
        if (!property_exists($teamJson, "id")) {
            $this->logger->error('could not find stdClass-property "id"');
            return null;
        }
        if (!property_exists($teamJson, 'shortName')) {
            $this->logger->error('could not find stdClass-property "shortName"');
            return null;
        }
        return new TeamData(
            (string)$teamJson->id,
            (string)$teamJson->shortName
        );
    }

}