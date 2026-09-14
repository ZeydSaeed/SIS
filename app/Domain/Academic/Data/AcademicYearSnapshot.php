<?php

namespace App\Domain\Academic\Data;

final readonly class AcademicYearSnapshot
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public string $startDate,
        public string $endDate,
        public bool $isCurrent,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
