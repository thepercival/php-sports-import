<?php
declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\Team as TeamBase;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<TeamBase>
 */
class Team extends AttacherBase
{
    public function __construct(
        protected TeamBase $team,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($team, $externalSource, $externalId);
    }
}