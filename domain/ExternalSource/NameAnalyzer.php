<?php

namespace SportsImport\ExternalSource;

class NameAnalyzer
{
    protected ?string $firstName;
    protected ?string $nameInsertions;
    protected ?string $lastName;

    protected static array $defaultNameInsertions = array(
        "van",
        "der",
        "de",
        "den",
        "te",
        "ten",
        "ter",
        "Van",
        "Der",
        "De",
        "Den",
        "Te",
        "Ten",
        "Ter"
    );

    public function __construct(string $name)
    {
        $this->firstName = null;
        $this->nameInsertions = null;
        $this->lastName = null;
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
        return $this->getLastName();
    }

    protected function analyse(string $name)
    {
        $arrNameParts = explode(" ", str_replace(".", "", $name));
        for ($nI = 0; $nI < count($arrNameParts); $nI++) {
            if ($nI === 0 and count($arrNameParts) > 1) {
                $this->firstName = $arrNameParts[$nI];
            } elseif ($nI < (count($arrNameParts) - 1)) {
                if (in_array($arrNameParts[$nI], static::$defaultNameInsertions, true) === true) {
                    if ($this->nameInsertions !== "") {
                        $this->nameInsertions .= " ";
                    }
                    $this->nameInsertions .= $arrNameParts[$nI];
                } else {
                    if ($this->lastName !== "") {
                        $this->lastName .= " ";
                    }
                    $this->lastName .= $arrNameParts[$nI];
                }
            } else {
                if ($nI === (count($arrNameParts) - 1)) {
                    if ($this->lastName !== "") {
                        $this->lastName .= " ";
                    }
                    $this->lastName .= $arrNameParts[$nI];
                }
            }
        }
    }
}
