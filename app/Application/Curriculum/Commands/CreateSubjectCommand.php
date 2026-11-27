<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class CreateSubjectCommand implements Command
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $nameEn,
        public int $subjectType,
        public ?int $creditHours,
        public int $maxGrade,
        public int $passGrade,
        public ?string $idempotencyKey,
        public ?string $prerequisitesText = null,
        /** @var list<int> every type of the subject; empty = just $subjectType */
        public array $subjectTypes = [],
        /** @var array<int|string, ?int> type → weekly hours of that type */
        public array $subjectTypeHours = [],
    ) {}
}
