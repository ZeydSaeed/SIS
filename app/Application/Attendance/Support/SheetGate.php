<?php

namespace App\Application\Attendance\Support;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Domain\Attendance\Data\AttendanceSheetSnapshot;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Exceptions\SessionCancelledException;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Domain\Attendance\Exceptions\SessionNotOpenException;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Services\LessonAccessGuard;
use App\Domain\Attendance\ValueObjects\LessonWindow;
use App\Domain\Attendance\ValueObjects\SessionStatus;

/**
 * Common checks before any sheet mutation (inside the handler's transaction): row lock, school + year scope,
 * cancellation, optimistic version, lesson ownership and the policy time window.
 */
final class SheetGate
{
    public function __construct(
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
    ) {}

    public function lock(int $sessionId, int $schoolId, int $academicYearId, int $expectedVersion): AttendanceSheetSnapshot
    {
        $sheet = $this->sheets->lockSheet($sessionId);
        if ($sheet === null) {
            throw SessionNotFoundException::forId($sessionId);
        }
        if (! $sheet->belongsTo($schoolId, $academicYearId)) {
            throw CrossSchoolAttendanceAccessException::create();
        }
        if ($sheet->isCancelled()) {
            throw SessionCancelledException::forId($sessionId);
        }
        if ($sheet->version !== $expectedVersion) {
            throw AttendanceRuleViolation::versionConflict($sessionId, $expectedVersion, $sheet->version);
        }

        return $sheet;
    }

    /** For marking / submitting: the sheet must be editable by this actor right now. */
    public function forTeacherWork(int $sessionId, int $schoolId, int $academicYearId, int $expectedVersion, SheetActor $actor): SheetContext
    {
        $sheet = $this->lock($sessionId, $schoolId, $academicYearId, $expectedVersion);
        if ($sheet->status !== SessionStatus::Open->value) {
            throw SessionNotOpenException::forId($sessionId, $sheet->status);
        }
        if (! $sheet->sheet()->allowsTeacherEditing()) {
            throw AttendanceRuleViolation::notEditable($sessionId);
        }
        LessonAccessGuard::assertCanWork($actor->teacherId, $sheet->teacherId, $sheet->coTeacherId, $actor->isReviewer);

        $policy = $this->policies->forSheet($sheet->policyId, $schoolId, $academicYearId);
        $window = $sheet->startTime !== null && $sheet->endTime !== null
            ? $policy->windowFor($sheet->sessionDate, $sheet->startTime, $sheet->endTime, $this->clock->now())
            : LessonWindow::Open;
        if (! $actor->isReviewer && $window === LessonWindow::NotYetOpen) {
            throw AttendanceRuleViolation::windowNotOpen();
        }
        if (! $actor->isReviewer && $window === LessonWindow::Closed) {
            throw AttendanceRuleViolation::windowClosed();
        }

        return new SheetContext($sheet, $policy, $window, $actor);
    }
}
