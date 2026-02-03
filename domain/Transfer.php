<?php

declare(strict_types=1);

namespace SportsImport;

use DateTimeImmutable;
use Sports\Person;
use Sports\Sport\FootballLine;
use Sports\Team;

final class Transfer
{
    public function __construct(
        public Person $person,
        public DateTimeImmutable $dateTime,
        public Team $fromTeam,
        public Team $toTeam,
        public FootballLine $toLine
    ) {
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function getDateTime(): DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function getFromTeam(): Team
    {
        return $this->fromTeam;
    }

    public function getToTeam(): Team
    {
        return $this->toTeam;
    }

    public function getToLine(): FootballLine
    {
        return $this->toLine;
    }
}
