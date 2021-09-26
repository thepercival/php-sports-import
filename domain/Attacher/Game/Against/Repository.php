<?php

namespace SportsImport\Attacher\Game\Against;

use SportsImport\Attacher\Game\Against as AgainstGameAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Game\Against as AgainstGame;

/**
 * @template-extends AttacherRepository<AgainstGameAttacher,AgainstGame>
 */
class Repository extends AttacherRepository
{
}