<?php

namespace App\Domain\Academic\Data;

final readonly class GradeLevelSnapshot
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
