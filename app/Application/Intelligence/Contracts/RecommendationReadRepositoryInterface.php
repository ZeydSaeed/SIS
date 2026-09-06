<?php

namespace App\Application\Intelligence\Contracts;

use App\Application\Intelligence\DTOs\RecommendationDetailDTO;
use App\Application\Intelligence\DTOs\RecommendationListItemDTO;
use App\Application\Intelligence\DTOs\RecommendationStatsDTO;

interface RecommendationReadRepositoryInterface
{
    /**
     * @return array{items: list<RecommendationListItemDTO>, pagination: array<string, mixed>}
     */
    public function paginateByStatus(string $status, int $perPage): array;

    public function findDetailById(int $id): ?RecommendationDetailDTO;

    public function getStats(bool $pgStatAvailable, ?float $databaseSizeMb, ?int $connectionCount): RecommendationStatsDTO;
}
