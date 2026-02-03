<?php

declare(strict_types=1);

namespace SportsImport;

/**
 * @api
 */
final class Entity
{
    public const int SPORTS = 1;
    public const int ASSOCIATIONS = 2;
    public const int SEASONS = 4;
    public const int LEAGUES = 8;
    public const int COMPETITIONS = 16;
    public const int STRUCTURE = 32;

    public const int TEAMCOMPETITORS = 64;
    public const int TEAMS = 128;
    public const int GAMES_BASICS = 256;
    public const int GAMES_COMPLEET = 512;
    public const int GAME = 1024;
    public const int PLAYERS = 2048;
    public const int TRANSFERS = 4096;
}
