<?php

namespace SportsImport\Attacher\League;

use SportsImport\Attacher\League as LeagueAttacher;
use SportsImport\Attacher\Repository as AttacherRepository;
use Sports\League as LeagueBase;

/**
 * @template-extends AttacherRepository<LeagueAttacher,LeagueBase>
 */
class Repository extends AttacherRepository
{
}