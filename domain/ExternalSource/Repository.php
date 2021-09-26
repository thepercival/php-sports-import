<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Doctrine\ORM\EntityRepository;
use SportsHelpers\Repository as BaseRepository;
use SportsImport\ExternalSource;

/**
 * @template-extends EntityRepository<ExternalSource>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<ExternalSource>
     */
    use BaseRepository;
}