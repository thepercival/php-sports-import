<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 4-3-18
 * Time: 19:49
 */

namespace Voetbal\ExternalSource;

use Voetbal\ExternalSource as ExternalSourceBase;

interface Implementation
{
    public function getExternalSource();
    public function setExternalSource(ExternalSourceBase $externalSource);
}
