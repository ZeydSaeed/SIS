<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Enrollment\Results\BulkEnrollStudentsResult;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Delegates each student to EnrollStudentHandler (same validation, capacity lock,
 * transaction and outbox per student). Domain rejections are collected per student
 * instead of aborting the batch; per-student idempotency keys derive from the batch key.
 */
final class BulkEnrollStudentsHandler implements CommandHandler
{
    public function __construct(
        private readonly EnrollStudentHandler $enroll,
    ) {}

    public function handle(Command $command): BulkEnrollStudentsResult
    {
        assert($command instanceof BulkEnrollStudentsCommand);

        $enrolled = [];
        $skipped = [];
        foreach (array_values(array_unique($command->studentIds)) as $studentId) {
            try {
                $this->enroll->handle($this->singleCommand($command, $studentId));
                $enrolled[] = $studentId;
            } catch (SisDomainException $e) {
                $skipped[] = ['student_id' => $studentId, 'error_code' => $e->errorCode()];
            }
        }

        return BulkEnrollStudentsResult::completed($enrolled, $skipped);
    }

    private function singleCommand(BulkEnrollStudentsCommand $command, int $studentId): EnrollStudentCommand
    {
        return new EnrollStudentCommand(
            schoolId: $command->schoolId,
            academicYearId: $command->academicYearId,
            studentId: $studentId,
            classId: $command->classId,
            sectionId: $command->sectionId,
            effectiveFrom: $command->effectiveFrom,
            branchId: $command->branchId,
            departmentId: $command->departmentId,
            enrolledBy: $command->enrolledBy,
            idempotencyKey: $command->idempotencyKey !== null
                ? $command->idempotencyKey.':'.$studentId
                : null,
        );
    }
}
