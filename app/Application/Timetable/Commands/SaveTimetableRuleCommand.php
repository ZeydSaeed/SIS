<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «قيد جديد»: a typed, prioritised, explicitly scoped constraint rule (catalogue: ConstraintRuleCatalogue). */
final readonly class SaveTimetableRuleCommand implements Command
{
    /**
     * @param  array<string, int|null>  $scope
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $ruleType,
        public int $priority,
        public array $scope,
        public array $params,
        public ?string $reason,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
