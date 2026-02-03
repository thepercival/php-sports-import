<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Team;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<Team>
 */
final class TeamAttacher extends AttacherAbstract
{
    public function __construct(
        protected Team $team,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($team, $externalSource, $externalId);
    }
}
