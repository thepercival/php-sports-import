<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class AgainstGameLineups
{
    public function __construct(
        public AgainstGameSidePlayers $home,
        public AgainstGameSidePlayers $away
    ) {
    }
}