<?php
declare(strict_types=1);

namespace SportsImport\Attacher\Competition;

use SportsImport\Attacher\Competition as CompetitionAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Competition as CompetitionBase;

/**
 * @template-extends AttacherRepository<CompetitionAttacher,CompetitionBase>
 */
class Repository extends AttacherRepository
{
}
