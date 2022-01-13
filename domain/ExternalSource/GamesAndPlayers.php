<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Competition;
use Sports\Game\Against as AgainstGame;

interface GamesAndPlayers
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

    // public function convertToPersonDEP( stdClass $externalPerson ): ?Person;

    public function getImagePlayer(string $personExternalId): string;

    /**
     * @param Competition $competition
     * @return list<int>
     */
    public function getGameRoundNumbers(Competition $competition): array;

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @return array<int|string, AgainstGame>
     */
    public function getAgainstGamesBasics(Competition $competition, int $gameRoundNumber): array;

    /**
     * @param Competition $competition
     * @param int $gameRoundNumber
     * @param bool $resetCache
     * @return array<int|string, AgainstGame>
     */
    public function getAgainstGamesComplete(Competition $competition, int $gameRoundNumber, bool $resetCache): array;

    public function getAgainstGame(Competition $competition, string|int $id, bool $resetCache): AgainstGame|null;
}
