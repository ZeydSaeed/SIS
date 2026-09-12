<?php

namespace App\Domain\Finance\Data;

final readonly class FeeTypeSnapshot
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
