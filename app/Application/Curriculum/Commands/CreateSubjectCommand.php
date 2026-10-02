<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class CreateSubjectCommand implements Command
{
    /**
     * @param  list<int>  $prerequisiteSubjectIds  Optional active subjects this subject requires.
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?string $nameEn,
        public int $subjectType,
        public ?int $creditHours,
        public int $maxGrade,
        public int $passGrade,
        public ?string $idempotencyKey,
        public array $prerequisiteSubjectIds = [],
    ) {}
}
