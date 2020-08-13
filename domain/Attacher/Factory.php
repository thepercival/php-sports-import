<?php

namespace SportsImport\Attacher;

use SportsImport\ExternalSource;
use SportsHelpers\Identifiable;
use SportsImport\Attacher as AttacherBase;
use SportsImport\Attacher\Sport as SportAttacher;
use SportsImport\Attacher\Association as AssociationAttacher;
use SportsImport\Attacher\Season as SeasonAttacher;
use SportsImport\Attacher\League as LeagueAttacher;
use SportsImport\Attacher\Competition as CompetitionAttacher;
use SportsImport\Attacher\CompetitorDep as CompetitorAttacher;
use Sports\Sport;
use Sports\Association;
use Sports\Season;
use Sports\League;
use Sports\Competition;
use Sports\Competitor;

class Factory
{
    public function createObject(Identifiable $importable, ExternalSource $externalSource, $externalId): ?AttacherBase
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
        } elseif ($importable instanceof Competitor) {
            return new CompetitorAttacher(
                $importable,
                $externalSource,
                $externalId
            );
        }
        return null;
    }
}
