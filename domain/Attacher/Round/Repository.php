<?php
declare(strict_types=1);

namespace SportsImport\Attacher\Round;

use SportsImport\Attacher\Round as RoundAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Round as RoundBase;

/**
 * @template-extends AttacherRepository<RoundAttacher,RoundBase>
 */
class Repository extends AttacherRepository
{
}
