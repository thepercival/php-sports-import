<?php

namespace SportsImport;

use DateTimeImmutable;

class CacheItemDb
{
    /**
     * @var int
     */
    protected $id;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var mixed
     */
    protected $value;
    /**
     * @var DateTimeImmutable
     */
    protected $expireDateTime;

    const MAX_LENGTH_NAME = 150;

    public function __construct(string $name, $value, DateTimeImmutable $expireDateTime = null)
    {
        $this->setName($name);
        $this->value = $value;
        $this->expireDateTime = $expireDateTime;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id = null)
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name)
    {
        if (strlen($name) > static::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                "de naam mag maximaal " . static::MAX_LENGTH_NAME . " karakters bevatten",
                E_ERROR
            );
        }

        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    public function getExpireDateTime(): ?DateTimeImmutable
    {
        return $this->expireDateTime;
    }

    public function setExpireDateTime(DateTimeImmutable $expireDateTime = null)
    {
        $this->expireDateTime = $expireDateTime;
    }
}
