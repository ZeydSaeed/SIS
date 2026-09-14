<?php

namespace App\Domain\Vocational\Data;

final readonly class TrackSnapshot
{
    public function __construct(
        public int $id,
        public int $specializationId,
        public int $schoolId,
        public string $code,
        public string $name,
        public int $status,
        public string $createdAt,
        public string $updatedAt,
    ) {}
}
