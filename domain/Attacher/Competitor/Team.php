<?php

declare(strict_types=1);

namespace SportsImport\Attacher\Competitor;

use Sports\Competitor\Team as TeamCompetitor;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<TeamCompetitor>
 */
class Team extends AttacherBase
{
    public function __construct(
        protected TeamCompetitor $teamCompetitor,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($teamCompetitor, $externalSource, $externalId);
    }
}
