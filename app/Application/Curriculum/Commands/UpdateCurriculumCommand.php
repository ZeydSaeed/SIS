<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateCurriculumCommand implements Command
{
    /**
     * @param  array{name?: string, specialization_id?: ?int}  $fields
     */
    public function __construct(
        public int $schoolId,
        public int $curriculumId,
        public array $fields,
        public ?string $idempotencyKey,
    ) {}
}
