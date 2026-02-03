<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Round;
use SportsImport\ExternalSource;

/**
 * @api
 * @template-extends AttacherAbstract<Round>
 */
final class RoundAttacher extends AttacherAbstract
{
    public function __construct(
        protected Round $round,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($round, $externalSource, $externalId);
    }
}
