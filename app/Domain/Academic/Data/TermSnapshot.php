<?php

namespace App\Domain\Academic\Data;

final readonly class TermSnapshot
{
    public function __construct(
        public int $id,
        public int $academicYearId,
        public string $code,
        public string $name,
        public string $startDate,
        public string $endDate,
        public int $termOrder,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
