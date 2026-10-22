<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Exams\Contracts\GradeEntryScopePort;
use App\Application\Exams\Contracts\GradeEntrySheetReadRepositoryInterface;
use App\Application\Exams\DTOs\SessionGradeRowDTO;
use App\Application\Exams\Results\EnterSessionGradesResult;
use App\Application\Exams\Support\SessionGradeRowGate;
use App\Domain\Exams\Support\GradeIdempotencyGuard;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Grade-entry sheet save: each row goes through the single official write path (EnterStudentGrade) in its own
 * transaction, with a per-row idempotency key derived from the submission key, so a retried submission replays
 * instead of duplicating and one rejected row never fails or hides the others.
 */
final class EnterSessionGradesHandler implements CommandHandler
{
    public function __construct(
        private readonly GradeEntrySheetReadRepositoryInterface $sheet,
        private readonly GradeEntryScopePort $scope,
        private readonly EnterStudentGradeHandler $enter,
    ) {}

    public function handle(Command $command): EnterSessionGradesResult
    {
        assert($command instanceof EnterSessionGradesCommand);

        $batchKey = GradeIdempotencyGuard::requireKey($command->idempotencyKey);
        $eligible = array_fill_keys(array_column($this->sheet->seats($command->schoolId, $command->examSessionId, null), 'exam_enrollment_id'), true);
        $allowed = $command->schoolWide
            ? $eligible
            : array_fill_keys($this->scope->coveredSeatIds($command->actorUserId, $command->schoolId, $command->examSessionId), true);

        $outcomes = [];
        $seen = [];
        foreach ($command->rows as $row) {
            $rejection = SessionGradeRowGate::rejection($row->examEnrollmentId, $eligible, $allowed, $seen);
            $seen[$row->examEnrollmentId] = true;
            $outcomes[] = $rejection !== null
                ? ['exam_enrollment_id' => $row->examEnrollmentId, 'status' => $rejection, 'grade_id' => null]
                : $this->enterRow($command, $row, $batchKey);
        }

        return EnterSessionGradesResult::fromOutcomes($outcomes);
    }

    /**
     * @return array{exam_enrollment_id: int, status: string, grade_id: ?int}
     */
    private function enterRow(EnterSessionGradesCommand $command, SessionGradeRowDTO $row, string $batchKey): array
    {
        try {
            $result = $this->enter->handle(new EnterStudentGradeCommand(
                schoolId: $command->schoolId,
                examEnrollmentId: $row->examEnrollmentId,
                score: $row->isAbsent ? null : $row->score,
                isAbsent: $row->isAbsent,
                enteredBy: $command->actorUserId,
                idempotencyKey: 'sheet:'.hash('sha256', $batchKey.'|'.$row->examEnrollmentId),
            ));
        } catch (SisDomainException $e) {
            return ['exam_enrollment_id' => $row->examEnrollmentId, 'status' => $e->errorCode(), 'grade_id' => null];
        }

        return [
            'exam_enrollment_id' => $row->examEnrollmentId,
            'status' => $result->fromIdempotencyCache ? 'replayed' : 'saved',
            'grade_id' => $result->gradeId,
        ];
    }
}
