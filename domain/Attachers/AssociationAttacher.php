<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Association as Association;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<Association>
 */
final class AssociationAttacher extends AttacherAbstract
{
    public function __construct(
        protected Association $association,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($association, $externalSource, $externalId);
    }
}
