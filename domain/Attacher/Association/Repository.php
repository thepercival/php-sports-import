<?php
declare(strict_types=1);

namespace SportsImport\Attacher\Association;

use SportsImport\Attacher\Association as AssociationAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Association as AssociationBase;

/**
 * @template-extends AttacherRepository<AssociationAttacher,AssociationBase>
 */
class Repository extends AttacherRepository
{
}