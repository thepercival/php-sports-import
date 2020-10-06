<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use SportsImport\ExternalSource\Association as ExternalSourceAssociation;
use Sports\Association as AssociationBase;
use Sports\Sport;
use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;
use SportsImport\Service as ImportService;

class Association extends SofaScoreHelper implements ExternalSourceAssociation
{
    /**
     * @var array|AssociationBase[]
     */
    protected $associationCache;
    /**
     * @var AssociationBase
     */
    protected $defaultAssociation;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->associationCache = [];
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    /**
     * @param Sport $sport
     * @return array|AssociationBase[]
     */
    public function getAssociations( Sport $sport ): array
    {
        $defaultAssociation = $this->getDefaultAssociation();
        $associations = [ $defaultAssociation->getId() => $defaultAssociation ];

        $externalAssociations = $this->apiHelper->getAssociationsData( $sport );

        foreach ($externalAssociations as $externalAssociation) {
            $association = $this->convertToAssociation($externalAssociation);
            $associations[$association->getId()] = $association;
        }
        return $associations;
    }

    public function getAssociation(Sport $sport, $id = null): ?AssociationBase
    {
        if (array_key_exists($id, $this->associationCache)) {
            return $this->associationCache[$id];
        }
        $associations = $this->getAssociations( $sport );
        if (array_key_exists($id, $associations)) {
            return $associations[$id];
        }
        return null;
    }

    /**
     * {"name":"England","slug":"england","priority":10,"id":1,"flag":"england"}
     *
     * @param stdClass $externalAssociation
     * @return AssociationBase
     * @throws \Exception
     */
    protected function convertToAssociation(\stdClass $externalAssociation): AssociationBase
    {
        if( array_key_exists( $externalAssociation->id, $this->associationCache ) ) {
            return $this->associationCache[$externalAssociation->id];
        }
        $association = new AssociationBase($externalAssociation->name);
        $association->setParent($this->getDefaultAssociation());
        $association->setId($externalAssociation->id);
        $this->associationCache[$association->getId()] = $association;
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
