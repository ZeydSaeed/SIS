<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\OpenExceptionalAttendanceResult;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\LessonType;
use App\Domain\Attendance\ValueObjects\SheetEventType;
use App\Domain\Attendance\ValueObjects\SheetStatus;

final class OpenExceptionalAttendanceHandler implements CommandHandler
{
    public const COMMAND_NAME = 'OpenExceptionalAttendance';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly AttendancePolicyResolver $policies,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): OpenExceptionalAttendanceResult
    {
        assert($command instanceof OpenExceptionalAttendanceCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return OpenExceptionalAttendanceResult::fromIdempotency($cached);
        }
        $reason = trim($command->reason);
        if ($reason === '') {
            throw AttendanceRuleViolation::reasonRequired();
        }
        if (! $this->attendance->academicYearContainsDate($command->academicYearId, $command->date)) {
            throw AttendanceRuleViolation::dateOutsideYear();
        }
        if ($this->sheets->isHoliday($command->schoolId, $command->academicYearId, $command->date)) {
            throw AttendanceRuleViolation::holiday($command->date);
        }
        $lesson = $this->sheets->exceptionalLesson($command->schoolId, $command->academicYearId, $command->sectionId, $command->subjectId, $command->teacherId, $command->periodId);
        if ($lesson === null) {
            throw AttendanceRuleViolation::exceptionalLessonInvalid('scope');
        }

        $snapshot = $lesson + ['date' => $command->date, 'reason' => $reason, 'exceptional' => true, 'lesson_type' => LessonType::infer((int) $lesson['subject_type'], null)->value];
        $policy = $this->policies->current($command->schoolId, $command->academicYearId);
        $opened = $this->unitOfWork->transaction(function () use ($command, $snapshot, $policy, $reason): array {
            $opened = $this->sheets->openExceptionalSession($command->schoolId, $command->academicYearId, $command->date, $snapshot, $policy->id, $command->actorUserId);
            if (! $opened['created']) {
                throw AttendanceRuleViolation::duplicateLesson();
            }
            $this->sheets->appendEvent($command->schoolId, $command->academicYearId, $opened['id'], SheetEventType::Opened, null, SheetStatus::Draft->value, 1, $command->actorUserId, 'exceptional: '.$reason, null);

            return $opened;
        });

        $data = ['session_id' => $opened['id']];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return OpenExceptionalAttendanceResult::success($data);
    }
}
