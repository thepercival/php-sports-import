<?php
declare(strict_types=1);

namespace SportsImport\Attacher\Competitor\Team;

use SportsImport\Attacher\Competitor\Team as TeamCompetitorAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\Competitor\Team as TeamCompetitor;

/**
 * @template-extends AttacherRepository<TeamCompetitorAttacher,TeamCompetitor>
 */
class Repository extends AttacherRepository
{
}