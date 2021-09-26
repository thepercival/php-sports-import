<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

use stdClass;

class AgainstGameHelper
{
    public function __construct(
        public int|string $id,
        public int|string $startTimestamp,
        public AgainstGameRound $roundInfo,
        public Status $status,
        public Team $homeTeam,
        public Team $awayTeam,
        public AgainstGameScore $homeScore,
        public AgainstGameScore $awayScore,
    ) {
    }
}