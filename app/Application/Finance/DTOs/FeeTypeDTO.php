<?php

namespace App\Application\Finance\DTOs;

final readonly class FeeTypeDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $name,
        public string $amount,
        public bool $isRecurring,
        public int $status,
        public string $createdAt,
    ) {}
}
