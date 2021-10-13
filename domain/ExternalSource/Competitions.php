<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource;

use Sports\Association;
use Sports\Competition;
use Sports\League;
use Sports\Season;
use Sports\Sport;

interface Competitions
{
    /**
     * @return array<int|string, Sport>
     */
    public function getSports(): array;
    public function getSport(string|int $id): Sport|null;
    /**
     * @param Sport $sport
     * @return array<int|string, Association>
     */
    public function getAssociations(Sport $sport): array;
    public function getAssociation(Sport $sport, string|int $id): Association|null;
    /**
     * @param Association $association
     * @return array<int|string, League>
     */
    public function getLeagues(Association $association): array;
    public function getLeague(Association $association, string|int $id): League|null;
    /**
     * @return array<int|string, Season>
     */
    public function getSeasons(): array;
    public function getSeason(string|int $id): Season|null;
    /**
     * @param Sport $sport,
     * @param League $league
     * @return array<int|string, Competition>
     */
    public function getCompetitions( Sport $sport, League $league): array;

    /**
     * @param Sport $sport,
     * @param League $league
     * @param Season $season
     * @return Competition|null
     */
    public function getCompetition( Sport $sport, League $league, Season $season): ?Competition;
}
