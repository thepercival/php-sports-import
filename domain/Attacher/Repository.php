<?php

namespace SportsImport\Attacher;

use SportsImport\ExternalSource;
use SportsImport\Attacher as AttacherBase;
use SportsHelpers\Identifiable;

class Repository extends \Sports\Repository
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
