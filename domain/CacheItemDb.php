<?php

declare(strict_types=1);

namespace SportsImport;

use DateTimeImmutable;
use SportsHelpers\Identifiable;

/**
 * @api
 */
final class CacheItemDb extends Identifiable
{
    public const MAX_LENGTH_NAME = 150;

    public function __construct(
        protected string $name,
        protected mixed $value,
        protected DateTimeImmutable|null $expireDateTime = null
    ) {
        $this->setName($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        if (strlen($name) > self::MAX_LENGTH_NAME) {
            throw new \InvalidArgumentException(
                "de naam mag maximaal " . self::MAX_LENGTH_NAME . " karakters bevatten",
                E_ERROR
            );
        }

        $this->name = $name;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function getExpireDateTime(): DateTimeImmutable|null
    {
        return $this->expireDateTime;
    }

    public function setExpireDateTime(DateTimeImmutable|null $expireDateTime): void
    {
        $this->expireDateTime = $expireDateTime;
    }
}
