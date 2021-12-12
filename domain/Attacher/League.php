<?php

declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\League as LeagueBase;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<LeagueBase>
 */
class League extends AttacherBase
{
    public function __construct(
        protected LeagueBase $league,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($league, $externalSource, $externalId);
    }
}
