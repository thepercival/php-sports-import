<?php

namespace SportsImport\ExternalSource;

use Sports\Association as AssociationBase;
use Sports\Sport;

interface Association
{
    /**
     * @param Sport $sport
     * @return array|AssociationBase[]
     */
    public function getAssociations( Sport $sport ): array;
    /**
     * @param Sport $sport
     * @param int|string $id
     * @return AssociationBase|null
     */
    public function getAssociation(Sport $sport, $id): ?AssociationBase;
}
