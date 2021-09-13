<?php

namespace SportsImport\Attacher\Association;

use Doctrine\ORM\EntityRepository;
use Sports\Association;
use SportsHelpers\Repository as BaseRepository;
use SportsImprt\Attacher\Repository as AttacherRepository;
use SportsHelpers\Repository\SaveRemove;
use SportsHelpers\Repository\SaveRemove as SaveRemoveRepository;

/**
 * @template-extends EntityRepository<Association>
 * @template-implements SaveRemoveRepository<Association>
 */
class Repository extends EntityRepository implements SaveRemoveRepository
{
    use BaseRepository;
    use AttacherRepository;
}