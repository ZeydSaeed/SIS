<?php

namespace App\Application\Enrollment\DTOs;

final readonly class EnrollmentListPageDTO
{
    /**
     * @param  list<EnrollmentDTO>  $items
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
            'data' => array_map(fn (EnrollmentDTO $item): array => $item->toArray(), $this->items),
            'meta' => $this->pagination,
        ];
    }
}
