<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

/**
 * Seat every active student of a class (or one of its sections) who has the session's subject assigned into an exam
 * session — one CreateExamEnrollment per student, so the seat rules and the audit trail are the single-seat ones.
 */
final readonly class SeatClassInExamSessionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $examId,
        public int $examSessionId,
        public int $classId,
        public ?int $sectionId,
        public int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
