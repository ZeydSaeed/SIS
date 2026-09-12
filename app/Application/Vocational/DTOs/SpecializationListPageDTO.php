<?php

namespace App\Application\Vocational\DTOs;

final readonly class SpecializationListPageDTO
{
    /**
     * @param  list<SpecializationDTO>  $items
     * @param  array{page:int,per_page:int,total:int,total_pages:int}  $pagination
     */
    public function __construct(
        public array $items,
        public array $pagination,
    ) {}
}
