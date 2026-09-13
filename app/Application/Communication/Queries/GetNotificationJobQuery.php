<?php

namespace App\Application\Communication\Queries;

final readonly class GetNotificationJobQuery
{
    public function __construct(
        public int $schoolId,
        public int $jobId,
    ) {}
}
