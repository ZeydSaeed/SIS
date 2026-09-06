<?php

namespace App\Application\Intelligence\DTOs;

final readonly class RecommendationStatsDTO
{
    public function __construct(
        public int $pendingCount,
        public int $approvedCount,
        public int $executedCount,
        public bool $pgStatAvailable,
        public ?float $databaseSizeMb,
        public ?int $connectionCount,
        public bool $intelligenceEnabled,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pending_count' => $this->pendingCount,
            'approved_count' => $this->approvedCount,
            'executed_count' => $this->executedCount,
            'pg_stat_available' => $this->pgStatAvailable,
            'database_size_mb' => $this->databaseSizeMb,
            'connection_count' => $this->connectionCount,
            'intelligence_enabled' => $this->intelligenceEnabled,
        ];
    }
}
