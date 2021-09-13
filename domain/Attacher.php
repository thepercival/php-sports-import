<?php
declare(strict_types=1);

namespace SportsImport;

use SportsHelpers\Identifiable;
use SportsImport\ExternalSource;

class Attacher
{
    /**
     * @var int
     */
    protected $id;
    /**
     * @var int
     */
    protected $importableId;

    const MAX_LENGTH_EXTERNALID = 100;

    public function __construct(
        protected Identifiable $importable,
        protected ExternalSource $externalSource,
        protected string $externalId
    )
    {
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id)
    {
        $this->id = $id;
    }

    public function getExternalId()
    {
        return $this->externalId;
    }

    /**
     * @return Identifiable
     */
    public function getImportable()
    {
        return $this->importable;
    }

    public function getExternalSource(): ExternalSource
    {
        return $this->externalSource;
    }

    /**
     * @return int
     */
    public function getImportableId(): int
    {
        return $this->importable->getId();
    }

    /**
     * @return int
     */
    public function getImportableIdForSer(): int
    {
        return $this->importableId;
    }

    /**
     * @param int $importableId
     */
    public function setImportableId(int $importableId)
    {
        $this->importableId = $importableId;
    }
}
