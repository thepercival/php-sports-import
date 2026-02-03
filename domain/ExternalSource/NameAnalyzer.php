<?php

namespace SportsImport\ExternalSource;

final class NameAnalyzer
{
    protected string|null $firstName = null;
    protected string|null $nameInsertions = null;
    protected string $lastName = '';

    /**
     * @var list<string>
     */
    protected static array $defaultNameInsertions = array(
        'van',
        'der',
        'de',
        'den',
        'te',
        'ten',
        'ter',
        'Bel',
        'Þór',
        'El'
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
        if (count($arrNameParts) ===  1) {
            $this->addLastNamePart($arrNameParts[0]);
            return;
        }

        for ($namePartNr = 1; $namePartNr <= count($arrNameParts); $namePartNr++) {
            $namePart = $arrNameParts[$namePartNr-1];

            // make sure LastName has priority
            if ($namePartNr === count($arrNameParts) && strlen($this->lastName) === 0) {
                $this->addLastNamePart($namePart);
                return;
            }

            $defaultNameInsertion = $this->getDefaultNameInsertions($namePart);
            if ($defaultNameInsertion !== null) {
                $this->addNameInsertionsPart($defaultNameInsertion);
                continue;
            }
            if ($namePartNr === 1) {
                $this->firstName = $namePart;
                continue;
            }
            $this->addLastNamePart($namePart);
        }
    }

    protected function addNameInsertionsPart(string $namePart): void
    {
        if ($this->nameInsertions !== null) {
            $this->nameInsertions .= " " . $namePart;
        } else {
            $this->nameInsertions = $namePart;
        }
    }

    protected function addLastNamePart(string $namePart): void
    {
        if (mb_strlen($this->lastName) > 0) {
            $this->lastName .= " ";
        }
        $this->lastName .= $namePart;
    }

    protected function inDefaultNameInsertions(string $nameInsertionInput): bool
    {
        return $this->getDefaultNameInsertions($nameInsertionInput) !== null;
    }

    protected function getDefaultNameInsertions(string $nameInsertionInput): string|null
    {
        foreach (static::$defaultNameInsertions as $value) {
            if (mb_strtolower($value) === mb_strtolower($nameInsertionInput)) {
                return $value;
            }
        }
        return null;
    }
}
