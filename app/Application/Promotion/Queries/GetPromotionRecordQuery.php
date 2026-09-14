<?php

namespace App\Application\Promotion\Queries;

final readonly class GetPromotionRecordQuery
{
    public function __construct(
        public int $schoolId,
        public int $recordId,
    ) {}
}
