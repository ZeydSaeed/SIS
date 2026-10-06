<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\AutoPlaceSectionResult;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Events\ScheduleCreated;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Services\TimetableAutoPlacer;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;

/**
 * «توزيع تلقائي»: plans each section in turn (the next one sees the teachers just booked) and writes
 * everything in one transaction.
 */
final class AutoPlaceSectionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AutoPlaceSection';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly TimetableBoardLoader $boards,
        private readonly TimetableAutoPlacer $placer,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AutoPlaceSectionResult
    {
        assert($command instanceof AutoPlaceSectionCommand);
        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AutoPlaceSectionResult::fromIdempotency((int) $cached['placed'], (int) $cached['unplaced']);
        }

        $board = $this->boards->load($command->schoolId, $command->academicYearId);
        if ($board->lessonPeriodIds === []) {
            return AutoPlaceSectionResult::failure('timetable.auto_no_periods');
        }
        $sectionIds = array_values(array_intersect(array_unique($command->sectionIds), array_column($board->requirements, 'section_id')));
        if ($sectionIds === []) {
            return AutoPlaceSectionResult::failure('timetable.auto_no_lessons');
        }

        $planned = [];
        $unplaced = 0;
        foreach ($sectionIds as $sectionId) {
            $plan = $this->placer->plan($board, $sectionId);
            $unplaced += $plan['unplaced'];
            $new = [];
            foreach ($plan['placements'] as $p) {
                $planned[] = ['section_id' => $sectionId] + $p;
                $new[] = ['id' => 0, 'section_id' => $sectionId, 'day_of_week' => $p['day'], 'period_id' => $p['period_id'], 'subject_id' => $p['subject_id'], 'teacher_id' => $p['teacher_id']];
            }
            $board = new TimetableBoard($board->periods, [...$board->schedules, ...$new], $board->requirements, $board->teacherSubjects, $board->activeTeacherIds, $board->practicalSubjectIds);
        }

        $this->unitOfWork->transaction(function () use ($command, $planned, $unplaced, $key): void {
            $at = now()->toIso8601String();
            foreach ($planned as $p) {
                $id = $this->schedules->insertActive(new PersistScheduleData(
                    schoolId: $command->schoolId,
                    sectionId: $p['section_id'],
                    academicYearId: $command->academicYearId,
                    dayOfWeek: $p['day'],
                    periodId: $p['period_id'],
                    subjectId: $p['subject_id'],
                    teacherId: $p['teacher_id'],
                    roomId: null,
                    at: $at,
                    correlationId: $command->correlationId,
                    createdBy: $command->createdBy,
                ));
                $this->outbox->stage(new ScheduleCreated($id, $command->schoolId, $p['section_id'], $command->academicYearId, $p['day'], $p['period_id'], new \DateTimeImmutable));
            }
            $this->idempotency->store($key, self::COMMAND_NAME, ['placed' => count($planned), 'unplaced' => $unplaced]);
        });

        return AutoPlaceSectionResult::success(count($planned), $unplaced);
    }
}
