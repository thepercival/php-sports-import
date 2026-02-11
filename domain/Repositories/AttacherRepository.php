<?php

declare(strict_types=1);

namespace SportsImport\Repositories;


use Doctrine\ORM\EntityRepository;
use SportsHelpers\Identifiable;
use SportsImport\Attachers\AttacherAbstract;
use SportsImport\ExternalSource;

/**
 * @api
 * @template T of object
 * @template-extends EntityRepository<T>
 */
final class AttacherRepository extends EntityRepository
{
    /**
     * @param ExternalSource $externalSource
     * @param string $externalId
     * @return T|null
     */
    public function findOneByExternalId(ExternalSource $externalSource, string $externalId): mixed
    {
        return $this->findOneBy(array(
            'externalId' => $externalId,
            'externalSource' => $externalSource
        ));
    }

    /**
     * @param ExternalSource $externalSource
     * @param Identifiable $importable
     * @return T|null
     */
    public function findOneByImportable(ExternalSource $externalSource, Identifiable $importable): mixed
    {
        return $this->findOneBy(array(
            'importable' => $importable,
            'externalSource' => $externalSource
        ));
    }
}
