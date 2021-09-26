<?php
declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\Data;

class AgainstGame
{
    /**
     * @var list<AgainstGameEvent>
     */
    public array $incidents = [];
    public AgainstGameLineups|null $lineups = null;

    public function __construct(public AgainstGameHelper $event)
    {
    }
}
