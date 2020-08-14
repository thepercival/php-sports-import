<?php

namespace SportsImport\ExternalSource\SofaScore\Helper;

use stdClass;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use SportsImport\ExternalSource\SofaScore\ApiHelper as SofaScoreApiHelper;
use SportsImport\ExternalSource\Association as ExternalSourceAssociation;
use Sports\Association as AssociationBase;
use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;
use SportsImport\Service as ImportService;

class Association extends SofaScoreHelper implements ExternalSourceAssociation
{
    /**
     * @var array|AssociationBase[]|null
     */
    protected $associations;
    /**
     * @var AssociationBase
     */
    protected $defaultAssociation;

    public function __construct(
        SofaScore $parent,
        SofaScoreApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        parent::__construct(
            $parent,
            $apiHelper,
            $logger
        );
    }

    public function getAssociations(): array
    {
        $this->initAssociations();
        return array_values($this->associations);
    }

    protected function initAssociations()
    {
        if ($this->associations !== null) {
            return;
        }
        $this->setAssociations($this->getAssociationData());
    }

    public function getAssociation($id = null): ?AssociationBase
    {
        $this->initAssociations();
        if (array_key_exists($id, $this->associations)) {
            return $this->associations[$id];
        }
        return null;
    }

    /**
     * @return array|stdClass[]
     */
    protected function getAssociationData(): array
    {
        $sports = $this->parent->getSports();
        $associationData = [];
        foreach ($sports as $sport) {
            if ($sport->getName() !== SofaScore::SPORTFILTER) {
                continue;
            }
            $apiData = $this->apiHelper->getCompetitionsData($sport);
            $associationData = array_merge($associationData, $apiData->sportItem->tournaments);
        }
        return $associationData;
    }

    /**
     * {"name":"England","slug":"england","priority":10,"id":1,"flag":"england"}
     *
     * @param array $competitions | stdClass[]
     */
    protected function setAssociations(array $competitions)
    {
        $defaultAssociation = $this->getDefaultAssociation();
        $this->associations = [ $defaultAssociation->getId() => $defaultAssociation ];

        foreach ($competitions as $competition) {
            if ($competition->category === null) {
                continue;
            }
            $name = $competition->category->name;
            if ($this->hasName($this->associations, $name)) {
                continue;
            }
            $association = $this->createAssociation($competition->category) ;
            $this->associations[$association->getId()] = $association;
        }
    }

    protected function createAssociation(\stdClass $category): AssociationBase
    {
        $association = new AssociationBase($category->name);
        $association->setParent($this->getDefaultAssociation());
        $association->setId($category->id);
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
