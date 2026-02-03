<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use SportsHelpers\Identifiable;
use SportsImport\ExternalSource;

/**
 * @template T
 */
abstract class AttacherAbstract extends Identifiable
{
    public const int MAX_LENGTH_EXTERNALID = 100;

    /**
     * @param T $importable
     * @param ExternalSource $externalSource
     * @param string $externalId
     */
    public function __construct(
        protected mixed $importable,
        protected ExternalSource $externalSource,
        protected string $externalId
    ) {
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getExternalSource(): ExternalSource
    {
        return $this->externalSource;
    }

    /**
     * @return T
     */
    public function getImportable(): mixed
    {
        return $this->importable;
    }
}
