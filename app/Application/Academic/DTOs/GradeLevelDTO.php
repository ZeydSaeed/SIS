<?php

namespace App\Application\Academic\DTOs;

final readonly class GradeLevelDTO
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public int $levelOrder,
        public int $educationStage,
        public int $status,
    ) {}
}
