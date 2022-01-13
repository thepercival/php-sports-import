<?php

declare(strict_types=1);

namespace SportsImport;

class Entity
{
    public const SPORTS = 1;
    public const ASSOCIATIONS = 2;
    public const SEASONS = 4;
    public const LEAGUES = 8;
    public const COMPETITIONS = 16;
    public const STRUCTURE = 32;

    public const TEAMCOMPETITORS = 64;
    public const TEAMS = 128;
    public const GAMES_BASICS = 256;
    public const GAMES_COMPLEET = 512;
    public const GAME = 1024;
    public const PLAYERS = 2048;
}
