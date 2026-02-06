<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Season;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherAbstract<Season>
 */
final class SeasonAttacher extends AttacherAbstract
{
    public function __construct(
        Season $season,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($season, $externalSource, $externalId);
    }
}
