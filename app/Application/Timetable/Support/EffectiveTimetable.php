<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableVersionRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Services\EffectiveVersionSelector;

/**
 * The timetable that governs one date (spec §115–120): the published version effective that day (or the working
 * grid when nothing is published yet), that weekday and cycle week, with the date's substitutions applied.
 */
final class EffectiveTimetable
{
    public function __construct(
        private readonly TimetableVersionRepositoryInterface $versions,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly TimetableEngineReadRepositoryInterface $engine,
        private readonly ScheduleExceptionRepositoryInterface $exceptions,
        private readonly EffectiveVersionSelector $selector,
    ) {}

    /**
     * @return array{lessons: list<array<string, mixed>>, version_id: int|null, day_of_week: int, week_no: int|null}
     */
    public function on(int $schoolId, int $academicYearId, \DateTimeImmutable $date, ?int $sectionId = null, ?int $teacherId = null): array
    {
        $day = $date->format('Y-m-d');
        $versionId = $this->selector->select($this->versions->listForYear($schoolId, $academicYearId), $day);
        $lessons = $versionId !== null
            ? array_map(static fn (array $e): array => ['schedule_id' => $e['source_schedule_id']] + $e, $this->versions->entries($schoolId, $versionId))
            : array_map(static fn (array $s): array => ['schedule_id' => $s['id']] + $s, $this->workspace->activeSchedules($schoolId, $academicYearId));

        $weekday = (int) $date->format('w') + 1;
        $cycle = $this->engine->settings($schoolId, $academicYearId)?->cycleWeeks ?? 1;
        $weekNo = null;
        $start = $this->engine->academicYearStart($academicYearId);
        if ($cycle > 1 && $start !== null) {
            $weekNo = intdiv(max(0, (int) (new \DateTimeImmutable($start))->diff($date)->days), 7) % $cycle + 1;
        }

        $subs = [];
        foreach ($this->exceptions->listForSchool($schoolId, null, $day, $day, 1, 1000)['items'] as $e) {
            $subs[$e->scheduleId] = $e;
        }
        $out = [];
        foreach ($lessons as $l) {
            if ($l['day_of_week'] !== $weekday || ($weekNo !== null && ($l['week_no'] ?? null) !== null && $l['week_no'] !== $weekNo)) {
                continue;
            }
            $sub = $l['schedule_id'] !== null ? ($subs[$l['schedule_id']] ?? null) : null;
            $l['substitute_teacher_id'] = $sub?->substituteTeacherId;
            $l['substitute_room_id'] = $sub?->substituteRoomId;
            $l['effective_teacher_id'] = $sub?->substituteTeacherId ?? $l['teacher_id'];
            if (($sectionId !== null && $l['section_id'] !== $sectionId)
                || ($teacherId !== null && ! in_array($teacherId, [$l['effective_teacher_id'], $l['co_teacher_id'] ?? null], true))) {
                continue;
            }
            $out[] = $l;
        }
        usort($out, static fn (array $a, array $b): int => [$a['section_id'], $a['period_id']] <=> [$b['section_id'], $b['period_id']]);

        return ['lessons' => $out, 'version_id' => $versionId, 'day_of_week' => $weekday, 'week_no' => $weekNo];
    }
}
