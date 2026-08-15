# Sofascore API Game Analysis
**PEC Zwolle 0–2 Feyenoord** | Eredivisie Round 34 (2025/26)  
Venue: Mac3Park Stadion, Zwolle | Referee: Erwin Blank

---

## Man of the Match (MOTM)

There is **no explicit MOTM field** in the API response. The best proxy is the `rating` field in `statistics`.

| Player | Team | Rating | Goals | Assists | xG | xA | Key Passes |
|---|---|---|---|---|---|---|---|
| **Anis Hadj Moussa** (#23, M) | Feyenoord | **10** | 2 (79', 87') | 0 | 0.49 | 1.03 | 6 |

Rating 10 is the maximum. Hadj Moussa scored both goals (left-foot) and had 6 key passes, making him the clear MOTM by rating.

> **Conclusion**: MOTM must be **derived from `statistics.rating`** — the player with the highest rating. No dedicated field exists.

---

## Player Unavailability

The `missingPlayers` array is present per team. Each entry has:
- `type`: `"missing"` (confirmed absent) or `"doubtful"` (uncertain)
- `reason`: integer — in this game **all entries are `1` (injury)**; no suspensions were present
- `description`: human-readable injury label
- `externalType`: Sofascore's internal injury category code
- `expectedEndDate`: estimated return date (when available)

### PEC Zwolle — Missing Players

| Player | Position | Type | Description | Expected Return |
|---|---|---|---|---|
| Samir Lagsir | M | missing | Muscle Injury | 2026-06-09 |
| Anselmo Garcia McNulty | D | doubtful | Hamstring | 2026-05-18 |
| Kaj de Rooij | F | doubtful | Unknown | 2026-05-18 |

### Feyenoord — Missing Players

| Player | Position | Type | Description | Expected Return |
|---|---|---|---|---|
| Anel Ahmedhodžić | D (#21) | missing | Unknown | 2026-06-09 |
| Bart Nieuwkoop | D (#2) | missing | Unknown | 2026-05-18 |
| Hwang In-beom | M (#6) | missing | Ankle Injury | 2026-06-08 |
| Jakub Moder | M (#7) | missing | Knee Injury | — |

### Unused Substitutes (on bench, never played)

Players with `substitute: true` and `minutesPlayed: 0` were in the squad but not used.

**PEC Zwolle** (8 unused): Tom de Graaff, Jasper Schendelaar, Scott Calderwood, Dylan Ruward, Rodney Kroeze, Nick Fichtinger, Gilles van der Wal, Maxim de Troije

**Feyenoord** (6 unused): Liam Bossin, Steven Benda, Jordan Lotomba, Aymen Sliti, Gonçalo Borges, Ilai Grootfaam

> **Summary of reason codes found**: Only `reason: 1` (injury) appears in this game. No suspensions (`reason: 2` or similar) were present. The `type` field distinguishes confirmed absent (`"missing"`) from uncertain (`"doubtful"`).

---

## Available Player Statistics

All statistics are in `players[n].statistics` inside `home.players` / `away.players`.

### Passing
| Field | Description |
|---|---|
| `totalPass` | Total passes attempted |
| `accuratePass` | Accurate passes |
| `totalLongBalls` / `accurateLongBalls` | Long ball attempts / accurate |
| `totalCross` / `accurateCross` | Cross attempts / accurate |
| `accurateOwnHalfPasses` / `totalOwnHalfPasses` | Passes in own half |
| `accurateOppositionHalfPasses` / `totalOppositionHalfPasses` | Passes in opposition half |
| `keyPass` | Key passes (leading to a shot) |
| `goalAssist` | Assists |
| `bigChanceCreated` | Big chances created |

### Shooting
| Field | Description |
|---|---|
| `totalShots` | Total shots |
| `onTargetScoringAttempt` | Shots on target |
| `shotOffTarget` | Shots off target |
| `blockedScoringAttempt` | Shots blocked |
| `goals` | Goals scored |
| `hitWoodwork` | Hits on woodwork |
| `expectedGoals` | xG |
| `expectedGoalsOnTarget` | xG on target |

### Duels & Defence
| Field | Description |
|---|---|
| `duelWon` / `duelLost` | Duels won/lost |
| `aerialWon` / `aerialLost` | Aerial duels won/lost |
| `totalTackle` / `wonTackle` | Tackle attempts / successful |
| `interceptionWon` | Interceptions |
| `totalClearance` | Clearances |
| `outfielderBlock` | Outfield blocks |
| `ballRecovery` | Ball recoveries |
| `fouls` / `wasFouled` | Fouls committed / suffered |
| `challengeLost` | Challenges lost |
| `dispossessed` | Times dispossessed |
| `errorLeadToAShot` | Errors leading to a shot |

### Ball Carrying & Movement
| Field | Description |
|---|---|
| `totalBallCarriesDistance` | Total distance carrying the ball (meters) |
| `ballCarriesCount` | Number of ball carries |
| `totalProgression` | Total progressive distance (meters) |
| `progressiveBallCarriesCount` | Progressive carries count |
| `bestBallCarryProgression` | Best single carry progression (meters) |
| `totalProgressiveBallCarriesDistance` | Total progressive carry distance |
| `unsuccessfulTouch` | Unsuccessful touches |
| `possessionLostCtrl` | Possession lost (controlled) |

### Physical / Tracking
| Field | Description |
|---|---|
| `minutesPlayed` | Minutes on pitch |
| `touches` | Total touches |
| `topSpeed` | Top speed (km/h) |
| `kilometersCovered` | Total distance covered (km) |
| `numberOfSprints` | Number of sprints |
| `metersCoveredRunningKm` | Distance running (km) |
| `metersCoveredHighSpeedRunningKm` | High-speed running distance (km) |
| `metersCoveredSprintingKm` | Sprinting distance (km) |

### Ratings & Normalized Values
| Field | Description |
|---|---|
| `rating` | Sofascore match rating (1–10) |
| `ratingVersions.original` / `.alternative` | Two rating versions |
| `expectedAssists` | xA |
| `shotValueNormalized` | Normalized shot contribution value |
| `passValueNormalized` | Normalized passing value |
| `dribbleValueNormalized` | Normalized dribble value |
| `defensiveValueNormalized` | Normalized defensive value |
| `goalkeeperValueNormalized` | Normalized GK value (GK only) |

### Goalkeeper-Specific
| Field | Description |
|---|---|
| `saves` | Total saves |
| `savedShotsFromInsideTheBox` | Saves from inside the box |
| `goodHighClaim` | Good high claims |
| `totalKeeperSweeper` / `accurateKeeperSweeper` | Sweeper-keeper actions |
| `keeperSaveValue` | Save value above expected |
| `goalsPrevented` | Goals prevented vs xG faced |
