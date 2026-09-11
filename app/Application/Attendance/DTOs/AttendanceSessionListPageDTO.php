<?php

namespace App\Application\Attendance\DTOs;

final readonly class AttendanceSessionListPageDTO
{
    /**
     * @param  list<AttendanceSessionDTO>  $items
     * @param  array<string, int>  $pagination
     */
    public function __construct(
        public array $items,
        public array $pagination,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn (AttendanceSessionDTO $item): array => $item->toArray(), $this->items),
            'meta' => $this->pagination,
        ];
    }
}
