<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class TeamCompetitor
{
    public function __construct(
        public string $id,
        public Team $team
    ) {
    }
}