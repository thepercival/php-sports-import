<?php

declare(strict_types=1);

namespace SportsImport\ExternalSource\SofaScore\ApiHelper;

use Psr\Log\LoggerInterface;
use Sports\Association;
use SportsImport\ExternalSource\SofaScore;
use SportsImport\ExternalSource\SofaScore\ApiHelper;
use SportsImport\ExternalSource\SofaScore\Data\League as LeagueData;
use SportsImport\Repositories\CacheItemDbRepository as CacheItemDbRepository;
use stdClass;

final class League extends ApiHelper
{
    public function __construct(
        SofaScore $sofaScore,
        CacheItemDbRepository $cacheItemDbRepos,
        LoggerInterface $logger
    ) {
        parent::__construct($sofaScore, $cacheItemDbRepos, $logger);
    }

    /**
     * @param Association $association
     * @return list<LeagueData>
     */
    public function getLeagues(Association $association): array
    {
        /** @var stdClass $apiData */
        $apiData = $this->getData(
            $this->getEndPoint($association),
            $this->getCacheId($association),
            $this->getCacheMinutes()
        );
        if (property_exists($apiData, "groups") === false) {
            $this->logger->error('could not find stdClass-property "categories"');
            return [];
        }
        $leagues = [];
        {
            /** @var list<stdClass> $groups */
            $groups = $apiData->groups;

            foreach ($groups as $group) {
                if (property_exists($group, "uniqueTournaments") === false) {
                    $this->logger->error('could not find stdClass-property "uniqueTournaments"');
                    continue;
                }
                /** @var list<stdClass> $groupLeagues */
                $groupLeagues = $group->uniqueTournaments;
                foreach ($groupLeagues as $groupLeague) {
                    $leagues[] = $this->convertApiDataRow($groupLeague);
                }
            }
        }

        return $leagues;
    }


    protected function convertApiDataRow(stdClass $apiDataRow): LeagueData
    {
        return new LeagueData(
            (string)$apiDataRow->id,
            (string)$apiDataRow->name
        );
    }

    public function getCacheId(Association $association): string
    {
        return $this->getEndPointSuffix($association);
    }

    #[\Override]
    public function getCacheMinutes(): int
    {
        return 60 * 24 * 30;
    }

    public function getDefaultEndPoint(): string
    {
        return "category/**categoryId**/unique-tournaments";
    }

    public function getEndPoint(Association $association): string
    {
        return $this->sofaScore->getExternalSource()->getApiurl() . $this->getEndPointSuffix($association);
    }

    protected function getEndPointSuffix(Association $association): string
    {
        $endpointSuffix = $this->getDefaultEndPoint();
        return str_replace("**categoryId**", (string)$association->getId(), $endpointSuffix);
    }
}
