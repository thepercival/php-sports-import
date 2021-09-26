<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use DateTimeImmutable;
use Sports\Person as PersonBase;
use Sports\Game;
use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\Person as ExternalSourcePerson;
use SportsImport\ExternalSource\NameAnalyzer;

/**
 * @template-extends SofaScoreHelper<PersonBase>
 */
class Person extends SofaScoreHelper implements ExternalSourcePerson
{
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
     * @param stdClass $externalPerson
     * @return ?PersonBase
     * @throws \Exception
     */
    public function convertToPerson(stdClass $externalPerson): ?PersonBase
    {
        $externalId = (string)$externalPerson->slug . "/" . (string)$externalPerson->id;
        if (array_key_exists($externalId, $this->cache)) {
            return $this->cache[$externalId];
        }
        $nameAnalyzer = new NameAnalyzer((string)$externalPerson->name);
        $firstName = $nameAnalyzer->getFirstName();
        if ($firstName === null) {
            $firstName = "Onbekend";
        }
        $person = new PersonBase($firstName, $nameAnalyzer->getNameInsertions(), $nameAnalyzer->getLastName());
        $person->setId($externalId);
        if (property_exists($externalPerson, "dateOfBirthTimestamp")) {
            $dateOfBirthTimestamp = (string)$externalPerson->dateOfBirthTimestamp;
            $person->setDateOfBirth(new DateTimeImmutable("@" . $dateOfBirthTimestamp));
        }
        $this->cache[$externalId] = $person;
        return $person;
    }

    public function getImagePerson(string $personExternalId): string
    {
        return $this->apiHelper->getPersonImageData($personExternalId);
    }
}
