<?php

declare(strict_types=1);

namespace SportsImport\Attacher;

use Doctrine\ORM\EntityRepository;
use SportsHelpers\Identifiable;
use SportsHelpers\Repository as BaseRepository;
use SportsImport\ExternalSource;

/**
 * @psalm-suppress MixedInferredReturnType, MixedReturnStatement, MixedMethodCall
 * @phpstan-ignore-next-line
 * @template T
 * @template I
 * @template-extends EntityRepository<T>
 */
class Repository extends EntityRepository
{
    /**
     * @use BaseRepository<T>
     */
    use BaseRepository;

    /**
     * @param ExternalSource $externalSource
     * @param string $externalId
     * @return T|null
     */
    public function findOneByExternalId(ExternalSource $externalSource, string $externalId): mixed
    {
        return $this->findOneBy(array(
            'externalId' => $externalId,
            'externalSource' => $externalSource
        ));
    }

    /**
     * @param ExternalSource $externalSource
     * @param string $externalId
     * @return I|null
     */
    public function findImportable(ExternalSource $externalSource, string $externalId): mixed
    {
        $attacher = $this->findOneByExternalId($externalSource, $externalId);
        if ($attacher === null) {
            return null;
        }
        return $attacher->getImportable();
    }

    /**
     * @param ExternalSource $externalSource
     * @param Identifiable $importable
     * @return T|null
     */
    public function findOneByImportable(ExternalSource $externalSource, Identifiable $importable): mixed
    {
        return $this->findOneBy(array(
            'importable' => $importable,
            'externalSource' => $externalSource
        ));
    }

    /**
     * @param ExternalSource $externalSource
     * @param Identifiable $importable
     * @return string|null
     */
    public function findExternalId(ExternalSource $externalSource, Identifiable $importable): string|null
    {
        $attacher = $this->findOneByImportable($externalSource, $importable);
        if ($attacher === null) {
            return null;
        }
        return $attacher->getExternalId();
    }
}
