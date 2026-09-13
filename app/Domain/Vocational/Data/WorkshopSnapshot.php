<?php

namespace App\Domain\Vocational\Data;

final readonly class WorkshopSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $name,
        public int $capacity,
        public int $safetyCapacity,
        public ?int $roomId,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
