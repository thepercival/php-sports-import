<?php
declare(strict_types=1);

namespace SportsImport\Attacher\Team;

use SportsImport\Attacher\Team as TeamAttacher;
use Sports\Team as TeamBase;
use SportsImport\Attacher\Repository as AttacherRepository;

/**
 * @template-extends AttacherRepository<TeamAttacher,TeamBase>
 */
class Repository extends AttacherRepository
{
}