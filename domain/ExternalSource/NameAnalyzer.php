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
        "ter"
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
        return $this->lastName;
    }

    protected function analyse(string $name)
    {
        $arrNameParts = explode(" ", str_replace(".", "", $name));
        for ($nI = 0; $nI < count($arrNameParts); $nI++) {
            if ($nI === 0 and count($arrNameParts) > 1) {
                $this->firstName = $arrNameParts[$nI];
            } elseif ($nI < (count($arrNameParts) - 1)) {
                if ($this->inDefaultNameInsertions($arrNameParts[$nI])) {
                    if ($this->nameInsertions !== null) {
                        $this->nameInsertions .= " ";
                    }
                    $this->nameInsertions .= strtolower($arrNameParts[$nI]);
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

    /**
     * @param string $needle
     * @return bool
     */
    protected function inDefaultNameInsertions( string $needle ): bool {
        foreach( static::$defaultNameInsertions as $value ) {
            if( strtolower($value) === $needle ) {
                return true;
            }
        }
        return false;
    }
}
