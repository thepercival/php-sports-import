<?php

namespace SportsImport\ExternalSource\SofaScore;

use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;

class Helper
{
    /**
     * @var SofaScore
     */
    protected $parent;
    /**
     * @var ApiHelper
     */
    protected $apiHelper;
    /**
     * @var LoggerInterface;
     */
    protected $logger;


    public function __construct(
        SofaScore $parent,
        ApiHelper $apiHelper,
        LoggerInterface $logger
    ) {
        $this->parent = $parent;
        $this->apiHelper = $apiHelper;
        $this->logger = $logger;
    }

    protected function hasName(array $objects, string $name): bool
    {
        foreach ($objects as $object) {
            if ($object->getName() === $name) {
                return true;
            }
        }
        return false;
    }


    private function notice($msg)
    {
        $this->logger->notice($this->parent->getExternalSource()->getName() . " : " . $msg);
    }

    private function error($msg)
    {
        $this->logger->error($this->parent->getExternalSource()->getName() . " : " . $msg);
    }
}
