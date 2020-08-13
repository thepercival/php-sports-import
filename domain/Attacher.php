<?php

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
     * @var Identifiable
     */
    protected $importable;
    /**
     * @var ExternalSource
     */
    protected $externalSource;
    /**
     * @var string
     */
    protected $externalId;
    /**
     * @var int
     */
    protected $importableId;

    const MAX_LENGTH_EXTERNALID = 100;

    public function __construct(Identifiable $importable, ExternalSource $externalSource, $externalId)
    {
        $this->setImportable($importable);
        $this->externalSource = $externalSource;
        $this->setExternalId($externalId);
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
     * @param string $externalId
     */
    public function setExternalId($externalId)
    {
        if (strlen($externalId) > static::MAX_LENGTH_EXTERNALID) {
            throw new \InvalidArgumentException("de externe id mag maximaal ".static::MAX_LENGTH_EXTERNALID." karakters bevatten", E_ERROR);
        }
        $this->externalId = $externalId;
    }

    /**
     * @return Identifiable
     */
    public function getImportable()
    {
        return $this->importable;
    }

    /**
     * @param Identifiable $importable
     */
    public function setImportable(Identifiable $importable)
    {
        $this->importable = $importable;
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
