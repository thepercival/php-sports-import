<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\Game;

use Sports\Competition;
use Sports\Game\Against as AgainstGame;

interface Against
{
    /**
     * @param Competition $competition
     * @return list<int>
     */
    public function getBatchNrs(Competition $competition): array;
    /**
     * @param Competition $competition
     * @param int $batchNr
     * @return array<int|string, AgainstGame>
     */
    public function getAgainstGames(Competition $competition, int $batchNr): array;
    public function getAgainstGame(Competition $competition, string|int $id): AgainstGame|null;
}
