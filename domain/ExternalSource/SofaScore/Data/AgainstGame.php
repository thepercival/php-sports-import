<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use DateTimeImmutable;
use Sports\Game\State as GameState;
use SportsHelpers\Against\Side;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Card as CardEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Goal as GoalEventData;
use SportsImport\ExternalSource\SofaScore\Data\AgainstGameEvent\Substitution as SubstitutionEventData;

class AgainstGame
{
    /**
     * @var list<CardEventData|GoalEventData|SubstitutionEventData>
     */
    public array $events = [];
    public AgainstGameLineups|null $players = null;

    public function __construct(
        public string $id,
        public DateTimeImmutable $start,
        public AgainstGameRound $roundInfo,
        public GameState $state,
        public Team $homeTeam,
        public Team $awayTeam,
        public AgainstGameScore $homeScore,
        public AgainstGameScore $awayScore,
    ) {
    }



    public function getPlayers(Side $side): AgainstGameSidePlayers|null {
        if( $this->players === null) {
            return null;
        }
        return $side === Side::Home ? $this->players->homePlayers : $this->players->awayPlayers;
    }
}
