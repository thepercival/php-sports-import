<?php
declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\Competition as CompetitionBase;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<CompetitionBase>
 */
class Competition extends AttacherBase
{
    public function __construct(
        protected CompetitionBase $competition,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($competition, $externalSource, $externalId);
    }
}
