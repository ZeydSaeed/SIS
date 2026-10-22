<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Exams\DTOs\SessionGradeRowDTO;

/**
 * Enter several grades of one exam session (the grade-entry sheet). `schoolWide` is the server-derived
 * grades.enter.any decision; without it every row must be covered by the actor's own teaching assignments.
 */
final readonly class EnterSessionGradesCommand implements Command
{
    /**
     * @param  list<SessionGradeRowDTO>  $rows
     */
    public function __construct(
        public int $schoolId,
        public int $examSessionId,
        public int $actorUserId,
        public bool $schoolWide,
        public array $rows,
        public ?string $idempotencyKey = null,
    ) {}
}
