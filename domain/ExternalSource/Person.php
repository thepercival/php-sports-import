<?php

namespace SportsImport\ExternalSource;

use Sports\Person as PersonBase;
use Sports\Game;
use stdClass;

interface Person
{
//    /**
//     * @param Game $game
//     * @return array|PersonBase[]
//     */
//    public function getPersons(Game $game): array;

//    /**
//     * @param Game $game
//     * @param string|int $id
//     * @return PersonBase|null
//     */
//    public function getPerson( Game $game, $id ): ?PersonBase;

    public function convertToPerson( stdClass $externalPerson ): ?PersonBase;

    public function getImagePerson( string $personExternalId ): string;
}
