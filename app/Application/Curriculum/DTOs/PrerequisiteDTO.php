<?php

namespace App\Application\Curriculum\DTOs;

final readonly class PrerequisiteDTO
{
    public function __construct(
        public int $id,
        public int $subjectId,
        public int $prerequisiteSubjectId,
        public int $status,
        public string $createdAt,
    ) {}
}
