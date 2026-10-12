<?php

namespace App\Application\Attendance\Support;

use App\Application\Attendance\Commands\SubmitAttendanceExcuseCommand;
use App\Domain\Attendance\Data\EnrollmentAttendanceContext;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Repositories\AttendanceExcuseRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;

/**
 * An excuse targets an enrollment of this school and year, a range inside the academic year (≤ 90 days), and —
 * when given — a session of the student's section and a document of the same school.
 */
final class ExcuseSubmissionValidator
{
    public function __construct(
        private readonly AttendanceExcuseRepositoryInterface $excuses,
        private readonly AttendanceWriteRepositoryInterface $attendance,
    ) {}

    public function validate(SubmitAttendanceExcuseCommand $command): EnrollmentAttendanceContext
    {
        $enrollment = $this->attendance->loadEnrollmentForMark($command->enrollmentId);
        if ($enrollment === null || $enrollment->schoolId !== $command->schoolId || $enrollment->academicYearId !== $command->academicYearId) {
            throw CrossSchoolAttendanceAccessException::create();
        }
        if ($command->dateTo < $command->dateFrom
            || ! $this->attendance->academicYearContainsDate($command->academicYearId, $command->dateFrom)
            || ! $this->attendance->academicYearContainsDate($command->academicYearId, $command->dateTo)) {
            throw AttendanceRuleViolation::excuseInvalid('dates');
        }
        if ((new \DateTimeImmutable($command->dateFrom))->diff(new \DateTimeImmutable($command->dateTo))->days > 90) {
            throw AttendanceRuleViolation::excuseInvalid('range');
        }
        if (trim($command->reason) === '') {
            throw AttendanceRuleViolation::reasonRequired();
        }
        if ($command->sessionId !== null && ! $this->excuses->sessionMatches($command->sessionId, $command->schoolId, $command->academicYearId, $enrollment->sectionId)) {
            throw AttendanceRuleViolation::excuseInvalid('session');
        }
        if ($command->documentFileId !== null && ! $this->excuses->documentBelongsToSchool($command->documentFileId, $command->schoolId)) {
            throw AttendanceRuleViolation::excuseInvalid('document');
        }

        return $enrollment;
    }
}
