<?php
/**
 * Created by PhpStorm.
 * User: coen
 * Date: 12-3-17
 * Time: 22:17
 */

namespace SportsImport;

use SportsHelpers\Identifiable;

interface ImporterInterface
{
    /**
     * @param ExternalSource $externalSource
     * @param array|Identifiable[] $importables
     */
    public function import(ExternalSource $externalSource, array $importables);
}
