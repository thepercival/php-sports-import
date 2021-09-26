<?php
declare(strict_types=1);

namespace SportsImport\Attacher;

use SportsImport\Attacher as AttacherBase;
use Sports\Association as AssociationBase;
use SportsImport\ExternalSource;

/**
 * @template-extends AttacherBase<AssociationBase>
 */
class Association extends AttacherBase
{
    public function __construct(
        protected AssociationBase $association,
        ExternalSource $externalSource,
        string $externalId
    ) {
        parent::__construct($association, $externalSource, $externalId);
    }
}
