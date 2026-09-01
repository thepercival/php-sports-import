<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

/**
 * @api
 */
final class PlayerProfile
{
    public function __construct(
        public Player $player,
        public Team $team
    ) {}
}