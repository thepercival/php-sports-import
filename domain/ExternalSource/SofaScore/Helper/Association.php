<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Association as AssociationApiHelper;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore;
use Sports\Association as AssociationBase;
use Sports\Sport;
use SportsImport\ExternalSource\SofaScore\Data\Association as AssociationData;

/**
 * @template-extends SofaScoreHelper<AssociationBase>
 */
final class Association extends SofaScoreHelper
{
    protected AssociationBase|null $defaultAssociation = null;

    public function __construct(
        protected AssociationApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @param Sport $sport
     * @return array<int|string, AssociationBase>
     */
    public function getAssociations(Sport $sport): array
    {
        $defaultAssociation = $this->getDefaultAssociation();
        $associations = [ (string)$defaultAssociation->getId() => $defaultAssociation ];

        $associationsData = $this->apiHelper->getAssociations($sport);

        foreach ($associationsData as $associationData) {
            $association = $this->convertDataToAssociation($associationData);
            $associations[$associationData->id] = $association;
        }
        return $associations;
    }

    public function getAssociation(Sport $sport, string|int $id): AssociationBase|null
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }
        $associations = $this->getAssociations($sport);
        if (array_key_exists($id, $associations)) {
            return $associations[$id];
        }
        return null;
    }

    protected function convertDataToAssociation(AssociationData $externalAssociation): AssociationBase
    {
        if (array_key_exists($externalAssociation->id, $this->cache)) {
            return $this->cache[$externalAssociation->id];
        }
        $association = new AssociationBase($externalAssociation->name);
        $association->setParent($this->getDefaultAssociation());
        $association->setId($externalAssociation->id);
        $this->cache[$externalAssociation->id] = $association;
        return $association;
    }

    protected function getDefaultAssociation(): AssociationBase
    {
        if ($this->defaultAssociation === null) {
            $this->defaultAssociation = new AssociationBase("FOOTBALLEARTH");
            $this->defaultAssociation->setId("FOOTBALLEARTH");
        }
        return $this->defaultAssociation;
    }
}
