<?php

namespace SportsImport\ExternalSource;

use Sports\Association as AssociationBase;

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
