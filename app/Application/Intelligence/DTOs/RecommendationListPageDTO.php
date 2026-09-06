<?php

namespace App\Application\Intelligence\DTOs;

final readonly class RecommendationListPageDTO
{
    /**
     * @param  list<RecommendationListItemDTO>  $items
     * @param  array<string, mixed>  $pagination
     */
    public function __construct(
        public array $items,
        public array $pagination,
        public RecommendationStatsDTO $stats,
        public string $statusFilter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toInertiaPayload(): array
    {
        return [
            'recommendations' => [
                'data' => array_map(fn (RecommendationListItemDTO $item) => $item->toArray(), $this->items),
                ...$this->pagination,
            ],
            'filters' => ['status' => $this->statusFilter],
            'stats' => $this->stats->toArray(),
        ];
    }
}
