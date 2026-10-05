<?php

namespace App\Domain\Organization\Data;

final readonly class SchoolSnapshot
{
    public function __construct(
        public int $id,
        public int $directorateId,
        public string $code,
        public string $name,
        public int $schoolType,
        public ?string $address,
        public ?string $phone,
        public ?string $email,
        public int $status,
    ) {}
}
