<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\Game;

use DateTimeImmutable;
use Sports\Team\Player;
use SportsHelpers\Against\AgainstSide;

/**
 * @api
 */
final class MissingPlayer
{
    public function __construct(
        public Player $player,
        public AgainstSide $againstSide,
        public string $type,
        public int $reason,
        public string $description,
        public int $externalType,
        public DateTimeImmutable|null $expectedEndDate
    ) {}
}
