<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Sport;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<Sport>
 */
final class SportAttacher extends AttacherAbstract
{
    public function __construct(
        protected Sport $sport,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($sport, $externalSource, $externalId);
    }
}
