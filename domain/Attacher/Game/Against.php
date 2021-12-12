<?php

declare(strict_types=1);

namespace SportsImport\Attacher\Game;

use Sports\Game\Against as AgainstGame;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<AgainstGame>
 */
class Against extends AttacherBase
{
    public function __construct(
        protected AgainstGame $againstGame,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($againstGame, $externalSource, $externalId);
    }
}
