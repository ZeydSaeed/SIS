<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateSubjectCommand implements Command
{
    /**
     * @param  array{name?: string, name_en?: ?string, subject_type?: int, credit_hours?: ?int, max_grade?: int, pass_grade?: int}  $fields
     */
    public function __construct(
        public int $subjectId,
        public array $fields,
        public ?string $idempotencyKey,
    ) {}
}
