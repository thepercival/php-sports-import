<?php
declare(strict_types=1);

namespace SportsImport\Attacher\Season;

use SportsImport\Attacher\Season as SeasonAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Season as SeasonBase;

/**
 * @template-extends AttacherRepository<SeasonAttacher,SeasonBase>
 */
class Repository extends AttacherRepository
{
}