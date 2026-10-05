<?php

namespace App\Domain\Organization\Data;

final readonly class DirectorateSnapshot
{
    public function __construct(
        public int $id,
        public int $ministryId,
        public string $code,
        public string $name,
        public ?string $region,
        public int $status,
    ) {}
}
