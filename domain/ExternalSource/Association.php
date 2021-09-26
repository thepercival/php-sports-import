<?php

namespace SportsImport\ExternalSource;

use Sports\Association as AssociationBase;
use Sports\Sport;

interface Association
{
    /**
     * @param Sport $sport
     * @return array<int|string, AssociationBase>
     */
    public function getAssociations(Sport $sport): array;
    public function getAssociation(Sport $sport, string|int $id): AssociationBase|null;
}
