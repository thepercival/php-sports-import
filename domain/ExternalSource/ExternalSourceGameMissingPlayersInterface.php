<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource;

use SportsImport\ExternalSource\Game\MissingPlayer;

/**
 * @api
 */
interface ExternalSourceGameMissingPlayersInterface
{
    /**
     * @return list<MissingPlayer>
     */
    public function getAgainstGameMissingPlayers(string|int $gameId): array;
}
