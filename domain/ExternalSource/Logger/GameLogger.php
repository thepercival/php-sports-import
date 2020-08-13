<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 16-4-19
 * Time: 16:00
 */

namespace Voetbal\ExternalSource\Logger;

use Voetbal\Competition;
use Voetbal\ExternalSource;
use Voetbal\Game;

interface GameLogger
{
    public function addGameNotFoundNotice(string $msg, Competition $competition);
    public function addExternalGameNotFoundNotice(string $msg, ExternalSource $externalSource, Game $game, Competition $competition);
    public function addExternalCompetitorNotFoundNotice(string $msg, ExternalSource $externalSource, string $externalSourceCompetitor);
}
