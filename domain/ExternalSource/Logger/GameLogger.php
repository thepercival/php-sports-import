<?php

namespace SportsImport\ExternalSource\Logger;

use Sports\Competition;
use SportsImport\ExternalSource;
use Sports\Game;

interface GameLogger
{
    public function addGameNotFoundNotice(string $msg, Competition $competition);
    public function addExternalGameNotFoundNotice(string $msg, ExternalSource $externalSource, Game $game, Competition $competition);
    public function addExternalCompetitorNotFoundNotice(string $msg, ExternalSource $externalSource, string $externalSourceCompetitor);
}
