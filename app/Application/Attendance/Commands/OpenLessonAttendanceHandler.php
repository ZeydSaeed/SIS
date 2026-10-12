<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Attendance\Results\OpenLessonAttendanceResult;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\Services\LessonAccessGuard;
use App\Domain\Attendance\ValueObjects\LessonWindow;
use App\Domain\Attendance\ValueObjects\SessionOrigin;
use App\Domain\Attendance\ValueObjects\SheetEventType;
use App\Domain\Attendance\ValueObjects\SheetStatus;

/**
 * The lesson must exist in the effective timetable of that date (published version or working grid, with the
 * date's substitution) and be taught by the actor; a reviewer may open it on the teacher's behalf. The lesson is
 * frozen into the session so later timetable edits never rewrite this sheet.
 */
final class OpenLessonAttendanceHandler implements CommandHandler
{
    public const COMMAND_NAME = 'OpenLessonAttendance';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly LessonSourceInterface $lessons,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): OpenLessonAttendanceResult
    {
        assert($command instanceof OpenLessonAttendanceCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return OpenLessonAttendanceResult::fromIdempotency($cached);
        }

        $lesson = $this->eligibleLesson($command);
        $policy = $this->policies->current($command->schoolId, $command->academicYearId);

        $opened = $this->unitOfWork->transaction(function () use ($command, $lesson, $policy): array {
            $opened = $this->sheets->openLessonSession($lesson, $command->schoolId, $command->academicYearId, $policy->id, SessionOrigin::Timetable->value, $command->actor->userId);
            if ($opened['created']) {
                $this->sheets->appendEvent($command->schoolId, $command->academicYearId, $opened['id'], SheetEventType::Opened, null, SheetStatus::Draft->value, 1, $command->actor->userId, $command->actor->isReviewer && ! $lesson->isTaughtBy($command->actor->teacherId ?? 0) ? 'on_behalf' : null, null);
            }

            return $opened;
        });

        $data = ['session_id' => $opened['id'], 'created' => $opened['created'], 'school_id' => $command->schoolId];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return OpenLessonAttendanceResult::success($data);
    }

    private function eligibleLesson(OpenLessonAttendanceCommand $command): LessonSlot
    {
        if (! $this->attendance->academicYearContainsDate($command->academicYearId, $command->date)) {
            throw AttendanceRuleViolation::dateOutsideYear();
        }
        if ($this->sheets->isHoliday($command->schoolId, $command->academicYearId, $command->date)) {
            throw AttendanceRuleViolation::holiday($command->date);
        }

        $lesson = $this->lessons->lesson($command->schoolId, $command->academicYearId, $command->date, $command->scheduleId);
        if ($lesson === null) {
            throw AttendanceRuleViolation::lessonNotFound();
        }
        LessonAccessGuard::assertCanWork($command->actor->teacherId, $lesson->effectiveTeacherId(), $lesson->coTeacherId, $command->actor->isReviewer);

        if (! $command->actor->isReviewer) {
            $window = $this->policies->current($command->schoolId, $command->academicYearId)
                ->windowFor($command->date, $lesson->startTime, $lesson->endTime, $this->clock->now());
            if ($window === LessonWindow::NotYetOpen) {
                throw AttendanceRuleViolation::windowNotOpen();
            }
            if ($window === LessonWindow::Closed) {
                throw AttendanceRuleViolation::windowClosed();
            }
        }

        return $lesson;
    }
}
