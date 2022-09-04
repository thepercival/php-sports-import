<?php

namespace SportsImport\Event;

use Sports\Person as PersonBase;
use Sports\Season;
use SportsImport\Event\Action\Person as PersonAction;

class Person
{
    public function __construct(
        protected PersonAction $action,
        protected PersonBase $person,
        protected Season $season/*,
        protected \DateTimeImmutable $dateTime*/
    )
    {
    }

    public function getAction(): PersonAction
    {
        return $this->action;
    }

    public function getPerson(): PersonBase
    {
        return $this->person;
    }

    public function getSeason(): Season
    {
        return $this->season;
    }

    /*public function getDateTime(): \DateTimeImmutable
    {
        return $this->dateTime;
    }*/
}
