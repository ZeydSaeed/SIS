<?php

namespace App\Application\Curriculum\DTOs;

final readonly class SubjectDTO
{
    public function __construct(
        public int $id,
        public string $code,
        public string $name,
        public ?string $nameEn,
        public int $subjectType,
        public ?int $creditHours,
        public int $maxGrade,
        public int $passGrade,
        public int $status,
        public ?string $prerequisitesText = null,
        public ?string $abbreviation = null,
        public ?int $colorHue = null,
        /** «نوع المادة» — every type of the subject (one or several, ascending); subjectType is the first. */
        public array $subjectTypes = [],
        /** @var array<int, ?int> type → weekly hours of that type (null = not given) */
        public array $subjectTypeHours = [],
    ) {}
}
