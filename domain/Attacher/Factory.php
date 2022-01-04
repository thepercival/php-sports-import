<?php

declare(strict_types=1);

namespace SportsImport\Attacher;

use Sports\Association;
use Sports\Competition;
use Sports\League;
use Sports\Season;
use Sports\Sport;
use SportsHelpers\Identifiable;
use SportsImport\Attacher\Association as AssociationAttacher;
use SportsImport\Attacher\Competition as CompetitionAttacher;
use SportsImport\Attacher\League as LeagueAttacher;
use SportsImport\Attacher\Season as SeasonAttacher;
use SportsImport\Attacher\Sport as SportAttacher;
use SportsImport\ExternalSource;

class Factory
{
    /**
     * @param Identifiable $importable
     * @param ExternalSource $externalSource
     * @param string $externalId
     * @return SportAttacher|AssociationAttacher|SeasonAttacher|LeagueAttacher|CompetitionAttacher|null
     */
    public function createObject(
        Identifiable $importable,
        ExternalSource $externalSource,
        string $externalId
    ): SportAttacher|AssociationAttacher|SeasonAttacher|LeagueAttacher|CompetitionAttacher|null {
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
