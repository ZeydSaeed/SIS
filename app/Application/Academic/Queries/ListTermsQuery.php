<?php

namespace App\Application\Academic\Queries;

final readonly class ListTermsQuery
{
    public function __construct(
        public ?int $academicYearId = null,
    ) {}
}
