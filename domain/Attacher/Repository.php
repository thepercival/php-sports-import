<?php

namespace SportsImport\Attacher;

use Doctrine\ORM\EntityRepository;
use SportsImport\ExternalSource;
use SportsHelpers\Identifiable;

/**
 * @template-extends EntityRepository<mixed>
 */
class Repository extends EntityRepository
{
    public function findOneByExternalId(ExternalSource $externalSource, $externalId)
    {
        return $this->findOneBy(array(
            'externalId' => $externalId,
            'externalSource' => $externalSource
        ));
    }

    public function findImportable(ExternalSource $externalSource, $externalId)
    {
        $externalObject = $this->findOneByExternalId($externalSource, $externalId);
        if ($externalObject === null) {
            return null;
        }
        return $externalObject->getImportable();
    }

    public function findOneByImportable(ExternalSource $externalSource, Identifiable $importable)
    {
        return $this->findOneBy(array(
            'importable' => $importable,
            'externalSource' => $externalSource
        ));
    }


    public function findExternalId(ExternalSource $externalSource, Identifiable $importable)
    {
        $externalObject = $this->findOneByImportable($externalSource, $importable);
        if ($externalObject === null) {
            return null;
        }
        return $externalObject->getExternalId();
    }
}
