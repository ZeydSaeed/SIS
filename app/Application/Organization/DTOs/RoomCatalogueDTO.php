<?php

namespace App\Application\Organization\DTOs;

/** Read model of «الغرف الدراسية». Rows carry `abbreviation_suggested` (shown while no abbreviation is set). */
final readonly class RoomCatalogueDTO
{
    /**
     * @param  list<array<string, mixed>>  $rooms
     * @param  list<array<string, mixed>>  $types
     * @param  list<array{id: int, name: string, departments: list<array{id: int, name: string}>}>  $branches
     * @param  array{total: int, active: int, practical: int, capacity: int, by_kind: array<int, int>}  $stats
     * @param  array{page: int, per_page: int, total: int, last_page: int}  $pagination
     */
    public function __construct(
        public array $rooms,
        public array $types,
        public array $branches,
        public array $stats,
        public array $pagination,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'rooms' => $this->rooms,
            'types' => $this->types,
            'branches' => $this->branches,
            'stats' => $this->stats,
            'pagination' => $this->pagination,
        ];
    }
}
