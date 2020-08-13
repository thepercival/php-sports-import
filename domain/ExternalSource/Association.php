<?php

namespace Voetbal\ExternalSource;

use Voetbal\Association as AssociationBase;

interface Association
{
    /**
     * @return array|AssociationBase[]
     */
    public function getAssociations(): array;
    /**
     * @param mixed $id
     * @return AssociationBase|null
     */
    public function getAssociation($id): ?AssociationBase;
}
