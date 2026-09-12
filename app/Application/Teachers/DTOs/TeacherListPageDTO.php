<?php

namespace App\Application\Teachers\DTOs;

final readonly class TeacherListPageDTO
{
    /**
     * @param  list<TeacherDTO>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
