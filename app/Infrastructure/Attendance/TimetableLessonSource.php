<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Timetable\Queries\GetEffectiveTimetableHandler;
use App\Application\Timetable\Queries\GetEffectiveTimetableQuery;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\ValueObjects\LessonType;
use Illuminate\Support\Facades\DB;

/**
 * Attendance's view of the effective timetable: the Timetable application query decides which lessons happen on
 * a date (version, cycle week, substitutions); this adapter only adds display names and the lesson type, in a
 * fixed number of batched lookups (no N+1).
 */
final class TimetableLessonSource implements LessonSourceInterface
{
    public function __construct(
        private readonly GetEffectiveTimetableHandler $effective,
    ) {}

    public function lessonsOn(int $schoolId, int $academicYearId, string $date, ?int $teacherId = null, ?int $sectionId = null): array
    {
        $dto = $this->effective->handle(new GetEffectiveTimetableQuery($schoolId, $academicYearId, $date, $sectionId, $teacherId));
        $raw = array_values(array_filter($dto?->lessons ?? [], static fn (array $l): bool => ($l['schedule_id'] ?? null) !== null));
        if ($raw === []) {
            return [];
        }

        $names = $this->lookups($raw);
        $lessons = [];
        foreach ($raw as $l) {
            $period = $names['periods'][$l['period_id']] ?? null;
            $subject = $names['subjects'][$l['subject_id']] ?? null;
            $section = $names['sections'][$l['section_id']] ?? null;
            if ($period === null || $subject === null || $section === null) {
                continue;
            }
            $roomId = $l['substitute_room_id'] ?? $l['room_id'] ?? null;
            $room = $roomId !== null ? ($names['rooms'][$roomId] ?? null) : null;
            $effectiveTeacher = (int) ($l['effective_teacher_id'] ?? $l['teacher_id']);
            $lessons[] = new LessonSlot(
                scheduleId: (int) $l['schedule_id'],
                date: $date,
                sectionId: (int) $l['section_id'],
                subjectId: (int) $l['subject_id'],
                teacherId: (int) $l['teacher_id'],
                coTeacherId: isset($l['co_teacher_id']) ? (int) $l['co_teacher_id'] : null,
                substituteTeacherId: isset($l['substitute_teacher_id']) ? (int) $l['substitute_teacher_id'] : null,
                periodId: (int) $l['period_id'],
                periodNumber: (int) $period->period_number,
                startTime: substr((string) $period->start_time, 0, 5),
                endTime: substr((string) $period->end_time, 0, 5),
                roomId: $roomId !== null ? (int) $roomId : null,
                groupId: isset($l['group_id']) ? (int) $l['group_id'] : null,
                lessonType: LessonType::infer((int) $subject->subject_type, $room !== null && $room->kind !== null ? (int) $room->kind : null)->value,
                subjectName: (string) $subject->name,
                sectionName: (string) $section->section_name,
                className: (string) $section->class_name,
                roomName: $room?->name !== null ? (string) $room->name : null,
                groupName: isset($l['group_id']) ? ($names['groups'][(int) $l['group_id']] ?? null) : null,
                teacherName: $names['teachers'][$effectiveTeacher] ?? '',
            );
        }
        usort($lessons, static fn (LessonSlot $a, LessonSlot $b): int => [$a->startTime, $a->sectionName] <=> [$b->startTime, $b->sectionName]);

        return $lessons;
    }

    public function lesson(int $schoolId, int $academicYearId, string $date, int $scheduleId): ?LessonSlot
    {
        foreach ($this->lessonsOn($schoolId, $academicYearId, $date) as $lesson) {
            if ($lesson->scheduleId === $scheduleId) {
                return $lesson;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $lessons
     * @return array{periods: array<int, object>, subjects: array<int, object>, sections: array<int, object>, rooms: array<int, object>, groups: array<int, string>, teachers: array<int, string>}
     */
    private function lookups(array $lessons): array
    {
        $ids = static fn (string ...$keys): array => array_values(array_unique(array_filter(array_merge(...array_map(
            static fn (string $key): array => array_map(static fn (array $l) => $l[$key] ?? null, $lessons),
            $keys,
        )), static fn ($v): bool => $v !== null)));

        return [
            'periods' => DB::table('timetable.periods')->whereIn('id', $ids('period_id'))->get(['id', 'period_number', 'start_time', 'end_time'])->keyBy('id')->all(),
            'subjects' => DB::table('curriculum.subjects')->whereIn('id', $ids('subject_id'))->get(['id', 'name', 'subject_type'])->keyBy('id')->all(),
            'sections' => DB::table('enrollment.sections as s')->join('enrollment.classes as c', 'c.id', '=', 's.class_id')
                ->whereIn('s.id', $ids('section_id'))->get(['s.id', 's.name as section_name', 'c.name as class_name'])->keyBy('id')->all(),
            'rooms' => DB::table('organization.rooms as r')->leftJoin('organization.room_types as t', 't.id', '=', 'r.room_type_id')
                ->whereIn('r.id', $ids('room_id', 'substitute_room_id'))->get(['r.id', 'r.name', 't.kind'])->keyBy('id')->all(),
            'groups' => DB::table('timetable.division_groups')->whereIn('id', $ids('group_id'))->pluck('name', 'id')->all(),
            'teachers' => DB::table('teachers.teachers')->whereIn('id', $ids('teacher_id', 'effective_teacher_id', 'co_teacher_id'))->pluck('full_name', 'id')->all(),
        ];
    }
}
