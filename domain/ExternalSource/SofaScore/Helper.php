<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore;

use SportsImport\ExternalSource\SofaScore;
use Psr\Log\LoggerInterface;

/**
 * @template T
 */
abstract class Helper
{
    /**
     * @var array<int|string, T>
     */
    protected array $cache = [];

    public function __construct(
        protected SofaScore $parent,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @param list<object> $objects
     * @param string $name
     * @return bool
     */
    protected function hasName(array $objects, string $name): bool
    {
        foreach ($objects as $object) {
            if (!method_exists($object, "getName")) {
                continue;
            }
            if ($object->getName() === $name) {
                return true;
            }
        }
        return false;
    }


    private function notice(string $msg): void
    {
        $this->logger->notice($this->parent->getExternalSource()->getName() . " : " . $msg);
    }

    private function error(string $msg): void
    {
        $this->logger->error($this->parent->getExternalSource()->getName() . " : " . $msg);
    }
}
