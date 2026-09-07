<?php

namespace App\Application\Student\DTOs;

final readonly class StudentListPageDTO
{
    /**
     * @param  list<StudentListItemDTO>  $items
     * @param  array{page: int, per_page: int, total: int, last_page: int}  $pagination
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
            'data' => array_map(fn (StudentListItemDTO $item): array => $item->toArray(), $this->items),
            'meta' => $this->pagination,
        ];
    }
}
