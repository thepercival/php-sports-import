<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\Game;

use Sports\Team\Player;
use SportsImport\ExternalSource\Game\Statistics\Category;

/** @api */
final readonly class ParticipationStatistics
{
    /**
     * @param list<Category> $categories
     */
    public function __construct(
        public Player $player,
        public array $categories,
        public bool $manOfTheMatch
    ) {}
}
