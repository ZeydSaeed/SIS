<?php

namespace App\Domain\Curriculum\Data;

/** Read model for vocational specialization → subject template rows. */
final readonly class SpecializationSubjectTemplate
{
    public function __construct(
        public int $subjectId,
        public ?int $creditHours,
        public bool $isRequired,
    ) {}
}
