<?php

namespace SportsImport\Event;

use Sports\Game\Against as AgainstGame;
use SportsImport\Event\Action\Game as GameAction;

/**
 * @api
 */
final class Game
{
    public function __construct(
        protected GameAction $action,
        protected AgainstGame $game,
        protected \DateTimeImmutable|null $oldDateTime
    ) {
    }

    public function getAction(): GameAction
    {
        return $this->action;
    }

    public function getGame(): AgainstGame
    {
        return $this->game;
    }

    public function getOldDateTime(): \DateTimeImmutable|null
    {
        return $this->oldDateTime;
    }
}
