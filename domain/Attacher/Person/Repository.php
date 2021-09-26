<?php
declare(strict_types=1);

namespace SportsImport\Attacher\Person;

use SportsImport\Attacher\Person as PersonAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Person as PersonBase;

/**
 * @template-extends AttacherRepository<PersonAttacher,PersonBase>
 */
class Repository extends AttacherRepository
{
}