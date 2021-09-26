<?php
declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\Season as SeasonBase;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<SeasonBase>
 */
class Season extends AttacherBase
{
    public function __construct(
        protected SeasonBase $season,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($season, $externalSource, $externalId);
    }
}