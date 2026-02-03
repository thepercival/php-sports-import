<?php

declare(strict_types=1);

namespace SportsImport\Attachers;

use Sports\Association;
use Sports\Competition;
use Sports\League;
use Sports\Season;
use Sports\Sport;
use SportsImport\Attachers\AssociationAttacher as AssociationAttacher;
use SportsImport\Attachers\CompetitionAttacher as CompetitionAttacher;
use SportsImport\Attachers\LeagueAttacher as LeagueAttacher;
use SportsImport\Attachers\SeasonAttacher as SeasonAttacher;
use SportsImport\Attachers\SportAttacher as SportAttacher;
use SportsImport\ExternalSource;

/**
 * @api
 */
final class AttacherFactory
{
    /**
     * @param SportAttacher|AssociationAttacher|SeasonAttacher|LeagueAttacher|CompetitionAttacher $importable
     * @param ExternalSource $externalSource
     * @param string $externalId
     * @return SportAttacher|AssociationAttacher|SeasonAttacher|LeagueAttacher|CompetitionAttacher
     */
    public function createObject(
        Sport|Association|Season|League|Competition $importable,
        ExternalSource $externalSource,
        string $externalId
    ): SportAttacher|AssociationAttacher|SeasonAttacher|LeagueAttacher|CompetitionAttacher {
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
        }
        return new CompetitionAttacher(
            $importable,
            $externalSource,
            $externalId
        );
    }
}
