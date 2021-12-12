<?php

declare(strict_types=1);

namespace SportsImport\Attacher;

use SportsImport\ExternalSource;
use SportsHelpers\Identifiable;
use SportsImport\Attacher as AttacherBase;
use SportsImport\Attacher\Sport as SportAttacher;
use SportsImport\Attacher\Association as AssociationAttacher;
use SportsImport\Attacher\Season as SeasonAttacher;
use SportsImport\Attacher\League as LeagueAttacher;
use SportsImport\Attacher\Competition as CompetitionAttacher;
use Sports\Sport;
use Sports\Association;
use Sports\Season;
use Sports\League;
use Sports\Competition;

class Factory
{
    public function createObject(Identifiable $importable, ExternalSource $externalSource, string $externalId): ?AttacherBase
    {
        if ($importable instanceof Sport) {
            return new SportAttacher(
                $importable,
                $externalSource,
                $externalId
            );
        } elseif ($importable instanceof Association) {
            return new AssociationAttacher(
                $importable,
                $externalSource,
                $externalId
            );
        } elseif ($importable instanceof Season) {
            return new SeasonAttacher(
                $importable,
                $externalSource,
                $externalId
            );
        } elseif ($importable instanceof League) {
            return new LeagueAttacher(
                $importable,
                $externalSource,
                $externalId
            );
        } elseif ($importable instanceof Competition) {
            return new CompetitionAttacher(
                $importable,
                $externalSource,
                $externalId
            );
        } /*elseif ($importable instanceof Competitor) {
            return new CompetitorAttacher(
                $importable,
                $externalSource,
                $externalId
            );
        }*/
        return null;
    }
}
