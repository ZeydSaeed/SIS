<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «توليد الجدول»: queues a generation run — mode, scope (partial regeneration), options (time budget, seed,
 * objectives) and an optional what-if scenario. Refused while the advisor finds blockers in the scope (except
 * in relaxed mode) or another run of the school-year is active.
 */
final readonly class QueueTimetableGenerationCommand implements Command
{
    /**
     * @param  array<string, mixed>  $scope  section_ids / teacher_ids / subject_ids / branch_id / department_id / class_id
     * @param  array<string, mixed>  $options  time_budget, seed, objectives, what_if
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $mode,
        public array $scope,
        public array $options,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}

    public function isWhatIf(): bool
    {
        return ($this->options['what_if'] ?? []) !== [];
    }
}
