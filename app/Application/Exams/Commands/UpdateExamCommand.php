<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateExamCommand implements Command
{
    /**
     * @param  ?int  $targetStatus  Lifecycle transition only (never Cancelled — use CancelExam)
     */
    public function __construct(
        public int $schoolId,
        public int $examId,
        public int $actorUserId,
        public string $idempotencyKey,
        public ?string $name = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?int $examTypeId = null,
        public ?int $termId = null,
        public ?int $targetStatus = null,
        public ?string $correlationId = null,
    ) {}
}
