<?php

namespace App\Application\Intelligence\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Intelligence\Contracts\DatabaseMonitoringPort;
use App\Application\Intelligence\Contracts\RecommendationReadRepositoryInterface;
use App\Application\Intelligence\DTOs\RecommendationListPageDTO;

final class ListRecommendationsHandler implements QueryHandler
{
    public function __construct(
        private readonly RecommendationReadRepositoryInterface $recommendations,
        private readonly DatabaseMonitoringPort $monitoring,
    ) {}

    public function handle(Query $query): RecommendationListPageDTO
    {
        assert($query instanceof ListRecommendationsQuery);

        $page = $this->recommendations->paginateByStatus($query->status, $query->perPage);
        $lastSnapshotSize = null;
        $lastSnapshotConnections = null;

        $stats = $this->recommendations->getStats(
            $this->monitoring->pgStatExtensionAvailable(),
            $lastSnapshotSize,
            $lastSnapshotConnections,
        );

        return new RecommendationListPageDTO(
            items: $page['items'],
            pagination: $page['pagination'],
            stats: $stats,
            statusFilter: $query->status,
        );
    }
}
