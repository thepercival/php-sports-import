<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use SportsImport\ExternalSource\Association as ExternalSourceAssociation;
use Sports\Association as AssociationBase;
use Sports\Sport;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\Data\Association as AssociationData;
use Psr\Log\LoggerInterface;

/**
 * @template-extends SofaScoreHelper<AssociationBase>
 */
class Association extends SofaScoreHelper implements ExternalSourceAssociation
{
    protected AssociationBase|null $defaultAssociation = null;

//    public function __construct(
//        SofaScore $parent,
//        SofaScoreApiHelper $apiHelper,
//        LoggerInterface $logger
//    ) {
//        parent::__construct(
//            $parent,
//            $apiHelper,
//            $logger
//        );
//    }

    /**
     * @param Sport $sport
     * @return array<int|string, AssociationBase>
     */
    public function getAssociations(Sport $sport): array
    {
        $defaultAssociation = $this->getDefaultAssociation();
        $associations = [ (string)$defaultAssociation->getId() => $defaultAssociation ];

        $externalAssociations = $this->apiHelper->getAssociationsData($sport);

        foreach ($externalAssociations as $externalAssociation) {
            $association = $this->convertToAssociation($externalAssociation);
            $associations[$externalAssociation->id] = $association;
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

    protected function convertToAssociation(AssociationData $externalAssociation): AssociationBase
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
