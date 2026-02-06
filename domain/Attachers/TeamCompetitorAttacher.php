<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Competitor\Team as TeamCompetitor;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<TeamCompetitor>
 */
final class TeamCompetitorAttacher extends AttacherAbstract
{
    public function __construct(
        TeamCompetitor $teamCompetitor,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($teamCompetitor, $externalSource, $externalId);
    }
}
