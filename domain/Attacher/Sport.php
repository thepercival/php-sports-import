<?php
declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\Sport as SportBase;
use SportsImport\Attacher as AttacherBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<SportBase>
 */
class Sport extends AttacherBase
{
    public function __construct(
        protected SportBase $sport,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($sport, $externalSource, $externalId);
    }
}