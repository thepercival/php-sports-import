<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Helper;

use Psr\Log\LoggerInterface;
use Sports\Association;
use Sports\Competitor\Team as TeamCompetitor;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper\Team as TeamApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\Team as TeamData;
use SportsImport\ExternalSource\SofaScore\Helper as SofaScoreHelper;
use Sports\Team as TeamBase;
use Sports\Competition;

/**
 * @template-extends SofaScoreHelper<TeamBase>
 */
class Team extends SofaScoreHelper
{
    public function __construct(
        protected TeamApiHelper $apiHelper,
        SofaScore $parent,
        LoggerInterface $logger
    ) {
        parent::__construct($parent, $logger);
    }

    /**
     * @param Competition $competition
     * @return list<TeamBase>
     */
    public function getTeams(Competition $competition): array
    {
        return array_map(function (TeamCompetitor $teamCompetitor): TeamBase {
            return $teamCompetitor->getTeam();
        }, $this->parent->getTeamCompetitors($competition));
    }

    public function getTeam(Competition $competition, string|int $id): TeamBase|null
    {
        $competitionTeams = $this->getTeams($competition);
        if (array_key_exists($id, $competitionTeams)) {
            return $competitionTeams[$id];
        }
        return null;
    }

    public function getImageTeam(string $teamExternalId): string
    {
        return $this->apiHelper->getImage($teamExternalId);
    }

    /**
     * {
     *   "name": "FC Smolevichi",
     *   "slug": "fc-smolevichi",
     *   "gender": "M",
     *   "disabled": false,
     *   "national": false,
     *   "id": 42964,
     *   "subTeams": []
     * }
     */
    public function convertDataToTeam(Association $association, TeamData $teamData): TeamBase
    {
        $teamId = $teamData->id;
        if (array_key_exists($teamId, $this->cache)) {
            return $this->cache[$teamId];
        }
        $team = new TeamBase($association, $teamData->shortName);
        $team->setId($teamId);
        $abbreviation = $teamData->shortName;
        $startPos = 0;
        if (str_contains(strtolower($abbreviation), "fc ")) {
            $startPos = 3;
        }

        $team->setAbbreviation(strtoupper(substr($abbreviation, $startPos, TeamBase::MAX_LENGTH_ABBREVIATION)));
        $this->cache[$teamId] = $team;
        return $team;
    }
}
