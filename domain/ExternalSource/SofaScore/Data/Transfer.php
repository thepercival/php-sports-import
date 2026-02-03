<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

/**
 * {"player":{..},"slug":"england","priority":10,"id":1,"flag":"england"}
 */
final class Transfer
{
    public function __construct(
        public Player $player,
        public \DateTimeImmutable $dateTime,
        public Team $transferFrom,
        public Team $transferTo
    ) {
    }
}
