<?php

namespace App\Application\Timetable\DTOs;

final readonly class ScheduleListPageDTO
{
    /**
     * @param  list<ScheduleDTO>  $items
     * @param  array{page:int,per_page:int,total:int,total_pages:int}  $pagination
     */
    public function __construct(
        public array $items,
        public array $pagination,
    ) {}
}
