<?php

namespace SportsImport\Event\Action;

enum Game: string
{
    case Create = 'game-create';
    case UpdateBasics = 'game-update-basics';
    case Reschedule = 'game-reschedule';
    case UpdateScoresLineupsAndEvents = 'game-update-scores-lines-and-events';
}
