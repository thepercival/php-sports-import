<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Game\Against as AgainstGame;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<AgainstGame>
 */
final class AgainstGameAttacher extends AttacherAbstract
{
    public function __construct(
        protected AgainstGame $againstGame,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($againstGame, $externalSource, $externalId);
    }
}
