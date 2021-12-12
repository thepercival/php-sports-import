<?php

declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\Person as PersonBase;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<PersonBase>
 */
class Person extends AttacherBase
{
    public function __construct(
        protected PersonBase $person,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($person, $externalSource, $externalId);
    }
}
