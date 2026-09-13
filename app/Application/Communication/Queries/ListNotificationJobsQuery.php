<?php

namespace App\Application\Communication\Queries;

final readonly class ListNotificationJobsQuery
{
    public function __construct(
        public int $schoolId,
        public ?int $status = null,
        public int $limit = 50,
    ) {}
}
