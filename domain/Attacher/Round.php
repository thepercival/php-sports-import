<?php

declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\Round as RoundBase;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<RoundBase>
 */
class Round extends AttacherBase
{
    public function __construct(
        protected RoundBase $round,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($round, $externalSource, $externalId);
    }
}
