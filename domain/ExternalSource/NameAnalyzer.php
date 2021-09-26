<?php

namespace SportsImport\ExternalSource;

class NameAnalyzer
{
    protected string|null $firstName = null;
    protected string|null $nameInsertions = null;
    protected string $lastName = '';

    /**
     * @var list<string>
     */
    protected static array $defaultNameInsertions = array(
        "van",
        "der",
        "de",
        "den",
        "te",
        "ten",
        "ter"
    );

    public function __construct(string $name)
    {
        $this->analyse($name);
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getNameInsertions(): ?string
    {
        return $this->nameInsertions;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    protected function analyse(string $name): void
    {
        $arrNameParts = explode(" ", str_replace(".", "", $name));
        for ($nI = 0; $nI < count($arrNameParts); $nI++) {
            $namePart = $arrNameParts[$nI];
            if ($nI === 0 and count($arrNameParts) > 1) {
                $this->firstName = $namePart;
            } elseif ($nI < (count($arrNameParts) - 1)) {
                if ($this->inDefaultNameInsertions($namePart)) {
                    $this->addNameInsertionsPart($namePart);
                }
            } else {
                if ($nI === (count($arrNameParts) - 1)) {
                    $this->addLastNamePart($namePart);
                }
            }
        }
    }

    protected function addNameInsertionsPart(string $namePart): void
    {
        if ($this->nameInsertions !== null) {
            $this->nameInsertions .= " " . strtolower($namePart);
        } else {
            $this->nameInsertions = strtolower($namePart);
        }
    }

    protected function addLastNamePart(string $namePart): void
    {
        if (strlen($this->lastName) > 0) {
            $this->lastName .= " ";
        }
        $this->lastName .= $namePart;
    }

    protected function inDefaultNameInsertions(string $needle): bool
    {
        foreach (static::$defaultNameInsertions as $value) {
            if (strtolower($value) === $needle) {
                return true;
            }
        }
        return false;
    }
}
