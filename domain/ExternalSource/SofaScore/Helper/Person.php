<?php

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

class Person extends SofaScoreHelper implements ExternalSourcePerson
{
    /**
     * @var array|PersonBase[]
     */
    protected $personCache;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->personCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    /**
     * @param Game $game
     * @param string|int $id
     * @return PersonBase|null
     */
    public function getPerson( Game $game, $id ): ?PersonBase {
        if( array_key_exists( $id, $this->personCache ) ) {
            return $this->personCache[$id];
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
     * @return PersonBase
     * @throws \Exception
     */
    public function convertToPerson( stdClass $externalPerson ): PersonBase {
        if( array_key_exists( $externalPerson->id, $this->personCache ) ) {
            return $this->personCache[$externalPerson->id];
        }
        $nameAnalyzer = new NameAnalyzer( $externalPerson->name );
        $person = new PersonBase( $nameAnalyzer->getFirstName(), $nameAnalyzer->getNameInsertions(), $nameAnalyzer->getLastName() );
        $person->setId( $externalPerson->slug );
        $person->setDateOfBirth(new DateTimeImmutable( "@" . $externalPerson->dateOfBirthTimestamp ) );
        $this->personCache[$person->getId()] = $person;
        return $person;
    }

}
