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
    /**
     * @param stdClass $externalPlayer
     * @return PersonBase|null
     */
    public function convertToPerson( stdClass $externalPlayer ): ?PersonBase;
}
