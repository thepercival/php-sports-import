<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Person;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<Person>
 */
final class PersonAttacher extends AttacherAbstract
{
    public function __construct(
        Person $person,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($person, $externalSource, $externalId);
    }
}
