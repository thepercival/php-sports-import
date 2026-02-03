<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Competition;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<Competition>
 */
final class CompetitionAttacher extends AttacherAbstract
{
    public function __construct(
        protected Competition $competition,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($competition, $externalSource, $externalId);
    }
}
