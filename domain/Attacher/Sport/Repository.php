<?php

declare(strict_types=1);

namespace SportsImport\Attacher\Sport;

use SportsImport\Attacher\Sport as SportAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Sport as SportBase;

/**
 * @template-extends AttacherRepository<SportAttacher,SportBase>
 */
class Repository extends AttacherRepository
{
}
