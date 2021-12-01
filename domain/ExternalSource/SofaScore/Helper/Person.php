<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use Sports\Person as PersonBase;
use Sports\Game;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Player as PlayerApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Player as PlayerData;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\NameAnalyzer;

/**
 * @template-extends SofaScoreHelper<PersonBase>
 */
class Person extends SofaScoreHelper
{
    public function __construct(
        protected PlayerApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    public function getPerson(Game $game, string|int $id): PersonBase|null
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }
        return null;
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
     *
     * @param PlayerData $playerData
     * @return ?PersonBase
     * @throws \Exception
     */
    public function convertDataToPerson(PlayerData $playerData): ?PersonBase
    {
        if (array_key_exists($playerData->id, $this->cache)) {
            return $this->cache[$playerData->id];
        }
        $nameAnalyzer = new NameAnalyzer($playerData->name);
        $firstName = $nameAnalyzer->getFirstName();
        if ($firstName === null) {
            $firstName = "Onbekend";
        }
        $person = new PersonBase($firstName, $nameAnalyzer->getNameInsertions(), $nameAnalyzer->getLastName());
        $person->setId($playerData->id);
        if ($playerData->dateOfBirth !== null ) {
            $person->setDateOfBirth($playerData->dateOfBirth);
        }
        $this->cache[$playerData->id] = $person;
        return $person;
    }
}
