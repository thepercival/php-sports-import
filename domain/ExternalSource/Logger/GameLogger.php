<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\Logger;

use Sports\Competition;
use SportsImport\ExternalSource;
use Sports\Game;

interface GameLogger
{
    public function addGameNotFoundNotice(string $msg, Competition $competition): void;
    public function addExternalGameNotFoundNotice(string $msg, ExternalSource $externalSource, Game $game, Competition $competition): void;
    public function addExternalCompetitorNotFoundNotice(string $msg, ExternalSource $externalSource, string $externalSourceCompetitor): void;
}
