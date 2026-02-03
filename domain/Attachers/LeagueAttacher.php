<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\League;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<League>
 */
final class LeagueAttacher extends AttacherAbstract
{
    public function __construct(
        protected League $league,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($league, $externalSource, $externalId);
    }
}
