<?php

declare(strict_types=1);

namespace SportsImport\Repositories;


use Doctrine\ORM\EntityRepository;
use SportsHelpers\Identifiable;
use SportsImport\ExternalSource;

/**
 * @api
 * @template T
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

    /**
     * @param ExternalSource $externalSource
     * @param Identifiable $importable
     * @return string|null
     */
    public function findExternalId(ExternalSource $externalSource, Identifiable $importable): string|null
    {
        $attacher = $this->findOneByImportable($externalSource, $importable);
        if ($attacher === null) {
            return null;
        }
        return $attacher->externalId;
    }
}
