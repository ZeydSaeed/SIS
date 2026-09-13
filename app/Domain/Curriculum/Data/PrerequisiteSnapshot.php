<?php

namespace App\Domain\Curriculum\Data;

final readonly class PrerequisiteSnapshot
{
    public function __construct(
        public int $id,
        public int $subjectId,
        public int $prerequisiteSubjectId,
        public int $status,
        public string $createdAt,
    ) {}
}
