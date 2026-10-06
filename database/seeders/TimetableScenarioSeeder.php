<?php

namespace Database\Seeders;

use App\Application\Shared\Results\ApplicationResult;
use App\Application\Teachers\Commands\AddTeachingAssignmentCommand;
use App\Application\Teachers\Commands\AddTeachingAssignmentHandler;
use App\Application\Teachers\Commands\AssignTeacherSubjectCommand;
use App\Application\Teachers\Commands\AssignTeacherSubjectHandler;
use App\Application\Teachers\Commands\UnlinkTeacherSubjectCommand;
use App\Application\Teachers\Commands\UnlinkTeacherSubjectHandler;
use App\Application\Timetable\Commands\ArrangeSchoolDayCommand;
use App\Application\Timetable\Commands\ArrangeSchoolDayHandler;
use App\Application\Timetable\Commands\AutoPlaceSectionCommand;
use App\Application\Timetable\Commands\AutoPlaceSectionHandler;
use App\Application\Timetable\Commands\CancelScheduleCommand;
use App\Application\Timetable\Commands\CancelScheduleHandler;
use App\Application\Timetable\Commands\CreatePeriodCommand;
use App\Application\Timetable\Commands\CreatePeriodHandler;
use App\Application\Timetable\Commands\CreateScheduleCommand;
use App\Application\Timetable\Commands\CreateScheduleHandler;
use App\Application\Timetable\Commands\ShiftScheduleCommand;
use App\Application\Timetable\Commands\ShiftScheduleHandler;
use App\Application\Timetable\Commands\SwapSchedulesCommand;
use App\Application\Timetable\Commands\SwapSchedulesHandler;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Database\SchemaHelper;
use App\Domain\Shared\Exceptions\SisDomainException;
use App\Domain\Timetable\Services\TimetableAuditor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * «الجدول الدراسي» scenario for اعدادية التحرير (current year), written through the real handlers:
 *
 * - the school day: 7 lessons (added only when the school has none), laid out by «توزيع الاستراحات
 *   تلقائياً» from 08:00 — 45-minute lessons, breaks of 5 / 5 / 15 / 5 / 10 / 5 minutes; الأحد → الخميس;
 * - 20 active teachers get class / section teaching for the 10 sections (curriculum weekly hours,
 *   theory and «عملي» subjects, ≤ 24 lessons a teacher a week);
 * - sections in every state:
 *     1–6  «توزيع تلقائي» — complete (two of them then get a «استبدال» and a «زحف»);
 *     7    partly placed (lessons left in the tray);
 *     8    over-placed + a subject three times in a day;
 *     9    a hole in a day + a practical split from its double;
 *     10   empty, except a teacher pushed past 6 lessons in one day;
 * - one teacher loses a subject afterwards → its lessons stay on the grid as audit errors.
 *
 * Each run rebuilds the grid from scratch: the year's active lessons are first taken off (soft cancel —
 * history kept), then placed again (lesson keys carry a per-run token). Teaching assignments and the
 * school day use stable idempotency keys, so they are written once.
 * Usage: php artisan db:seed --class=TimetableScenarioSeeder
 */
final class TimetableScenarioSeeder extends Seeder
{
    private const SCHOOL = 'اعدادية التحرير';

    private const TEACHERS = 20;

    private const MAX_TEACHER_WEEK = 24;

    /** Weekly lessons each section aims for (the grid has 5 × 7 = 35 cells). */
    private const SECTION_TARGET = 27;

    private const LESSONS_PER_DAY = 7;

    private const DAY_START = '08:00';

    private const LESSON_MINUTES = 45;

    private int $schoolId = 0;

    /** Per-run token in lesson idempotency keys (a re-run rebuilds the grid). */
    private string $run = '';

    private int $yearId = 0;

    /** @var list<int> lesson periods in day order */
    private array $lessonPeriods = [];

    /** @var list<array{id: int, class_id: int, label: string}> */
    private array $sections = [];

    public function run(): void
    {
        $this->run = 'seed-timetable:'.now()->format('YmdHis');
        $this->resolveReferences();
        $this->ensurePeriods();
        $this->restoreOrphans();
        $this->assignTeaching();
        $this->clearGrid();

        [$s1, $s2, $s3, $s4, $s5, $s6, $s7, $s8, $s9, $s10] = array_column($this->sections, 'id');

        foreach ([$s1, $s2, $s3, $s4, $s5, $s6, $s7, $s8, $s9] as $sectionId) {
            $this->autoPlace($sectionId);
        }

        $this->swapFirstTwo($s1);
        $this->shiftDayLater($s2);
        $this->cancelEvery($s7, 3, 'partly');
        $this->overPlace($s8);
        $this->holeAndSplit($s9);
        $this->overloadTeacherDay($s10, 3);
        $this->orphanOneSubject($s3);

        $this->report();
    }

    private function resolveReferences(): void
    {
        $this->schoolId = (int) (DB::table(SchemaHelper::qualified('organization', 'schools'))->where('name', self::SCHOOL)->value('id')
            ?? throw new RuntimeException('Missing school '.self::SCHOOL));
        $this->yearId = (int) (DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('is_current', true)->value('id')
            ?? throw new RuntimeException('No current academic year.'));
        $this->bindSchool();

        foreach (DB::table(SchemaHelper::qualified('enrollment', 'sections').' as s')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 's.class_id')
            ->where('c.school_id', $this->schoolId)->where('c.academic_year_id', $this->yearId)
            ->where('c.status', 1)->where('s.status', 1)
            ->orderBy('c.id')->orderBy('s.id')
            ->get(['s.id', 's.class_id', 'c.name as class_name', 's.name']) as $row) {
            $this->sections[] = ['id' => (int) $row->id, 'class_id' => (int) $row->class_id, 'label' => $row->class_name.' — '.$row->name];
        }
        if (count($this->sections) < 10) {
            throw new RuntimeException('TimetableScenarioSeeder needs 10 active sections in '.self::SCHOOL.' (found '.count($this->sections).').');
        }
        $this->sections = array_slice($this->sections, 0, 10);
    }

    /**
     * The school day: 7 lessons when the school has none, then «توزيع الاستراحات تلقائياً» lays it out
     * (lesson periods keep their ids — lessons already on the grid stay on them).
     */
    private function ensurePeriods(): void
    {
        $periods = DB::table(SchemaHelper::qualified('timetable', 'periods'));
        if (! (clone $periods)->where('school_id', $this->schoolId)->exists()) {
            for ($number = 1; $number <= self::LESSONS_PER_DAY; $number++) {
                $start = sprintf('%02d:00', 7 + $number);
                $this->expect($this->handler(CreatePeriodHandler::class)->handle(new CreatePeriodCommand(
                    $this->schoolId, $number, $start, sprintf('%02d:45', 7 + $number), 1, 'seed-timetable:period:'.$number,
                )), 'period '.$number);
            }
        }
        $this->expect($this->handler(ArrangeSchoolDayHandler::class)->handle(new ArrangeSchoolDayCommand(
            $this->schoolId, self::DAY_START, self::LESSON_MINUTES, $this->run.':arrange',
        )), 'arrange the school day');

        $this->bindSchool();
        $this->lessonPeriods = DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('school_id', $this->schoolId)->where('period_type', 1)
            ->orderBy('period_number')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * 20 active teachers with department teaching get the same subject for whole sections, rotating so
     * each section collects ~27 weekly lessons of distinct subjects and no teacher passes 24 a week.
     * Lessons the database already binds to a class / section count towards both totals.
     */
    private function assignTeaching(): void
    {
        $board = $this->handler(TimetableBoardLoader::class)->load($this->schoolId, $this->yearId);
        $sectionLoad = [];
        $teacherLoad = [];
        $sectionSubjects = [];
        foreach ($board->requirements as $r) {
            $sectionLoad[$r['section_id']] = ($sectionLoad[$r['section_id']] ?? 0) + (int) $r['weekly'];
            $teacherLoad[$r['teacher_id']] = ($teacherLoad[$r['teacher_id']] ?? 0) + (int) $r['weekly'];
            $sectionSubjects[$r['section_id']][$r['subject_id']] = true;
        }

        $pool = $this->teachingPool();
        if ($pool === []) {
            throw new RuntimeException('No department teaching found — run TeachersScenarioSeeder first.');
        }

        foreach ($this->sections as $index => $section) {
            $count = count($pool);
            for ($step = 0; $step < $count && ($sectionLoad[$section['id']] ?? 0) < self::SECTION_TARGET; $step++) {
                $t = $pool[($index * 5 + $step) % $count];
                if (isset($sectionSubjects[$section['id']][$t['subject_id']])
                    || ($teacherLoad[$t['teacher_id']] ?? 0) + $t['weekly'] > self::MAX_TEACHER_WEEK
                    || ($sectionLoad[$section['id']] ?? 0) + $t['weekly'] > self::SECTION_TARGET + 1) {
                    continue;
                }

                $result = $this->handler(AddTeachingAssignmentHandler::class)->handle(new AddTeachingAssignmentCommand(
                    schoolId: $this->schoolId,
                    academicYearId: $this->yearId,
                    teacherId: $t['teacher_id'],
                    subjectId: $t['subject_id'],
                    branchId: $t['branch_id'],
                    departmentId: $t['department_id'],
                    classId: $section['class_id'],
                    sectionId: $section['id'],
                    idempotencyKey: 'seed-timetable:teach:'.$t['teacher_id'].':'.$t['subject_id'].':'.$section['id'],
                ));
                if ($result->failed() && ! in_array('teachers.assignment_exists', $result->errors, true)) {
                    $this->expect($result, 'teaching '.$t['subject_id'].' by '.$t['teacher_id'].' in '.$section['label']);
                }

                $sectionLoad[$section['id']] = ($sectionLoad[$section['id']] ?? 0) + $t['weekly'];
                $teacherLoad[$t['teacher_id']] = ($teacherLoad[$t['teacher_id']] ?? 0) + $t['weekly'];
                $sectionSubjects[$section['id']][$t['subject_id']] = true;
            }
        }
    }

    /**
     * Department teaching (no class) of the first 20 active teachers, with the curriculum's weekly hours.
     *
     * @return list<array{teacher_id: int, subject_id: int, branch_id: int, department_id: int, weekly: int}>
     */
    private function teachingPool(): array
    {
        $this->bindSchool();
        $rows = DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments').' as ta')
            ->join(SchemaHelper::qualified('teachers', 'teachers').' as t', 't.id', '=', 'ta.teacher_id')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', function ($join): void {
                $join->on('c.department_id', '=', 'ta.department_id')->on('c.academic_year_id', '=', 'ta.academic_year_id')->on('c.school_id', '=', 'ta.school_id');
            })
            ->join(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs', function ($join): void {
                $join->on('cs.curriculum_id', '=', 'c.id')->on('cs.subject_id', '=', 'ta.subject_id');
            })
            ->where('ta.school_id', $this->schoolId)->where('ta.academic_year_id', $this->yearId)
            ->where('ta.status', 1)->whereNull('ta.class_id')->whereNotNull('ta.department_id')
            ->where('t.status', 1)->where('c.status', 1)->where('cs.status', 1)->whereNotNull('cs.weekly_hours')
            ->orderBy('t.employee_code')->orderBy('ta.id')
            ->get(['ta.teacher_id', 'ta.subject_id', 'ta.branch_id', 'ta.department_id', 'cs.weekly_hours']);

        $teachers = [];
        $pool = [];
        foreach ($rows as $row) {
            $teacherId = (int) $row->teacher_id;
            if (! isset($teachers[$teacherId]) && count($teachers) >= self::TEACHERS) {
                continue;
            }
            $teachers[$teacherId] = true;
            $key = $teacherId.':'.$row->subject_id;
            $pool[$key] ??= [
                'teacher_id' => $teacherId,
                'subject_id' => (int) $row->subject_id,
                'branch_id' => (int) $row->branch_id,
                'department_id' => (int) $row->department_id,
                'weekly' => (int) $row->weekly_hours,
            ];
        }

        return array_values($pool);
    }

    /**
     * A previous run took a subject from a teacher (the audit-error scenario): give it back — subject and
     * section teaching — so this run rebuilds the same scenario instead of consuming another teacher.
     */
    private function restoreOrphans(): void
    {
        $this->bindSchool();
        $sectionIds = array_column($this->sections, 'id');
        $ended = DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments').' as ta')
            ->where('ta.school_id', $this->schoolId)->where('ta.academic_year_id', $this->yearId)
            ->where('ta.status', 2)->whereIn('ta.section_id', $sectionIds)
            ->whereNotExists(function ($q): void {
                $q->selectRaw('1')->from(SchemaHelper::qualified('teachers', 'teacher_subjects').' as ts')
                    ->whereColumn('ts.teacher_id', 'ta.teacher_id')->whereColumn('ts.subject_id', 'ta.subject_id')
                    ->whereColumn('ts.academic_year_id', 'ta.academic_year_id')->whereColumn('ts.school_id', 'ta.school_id');
            })
            ->orderBy('ta.id')
            ->get(['ta.teacher_id', 'ta.subject_id']);
        if ($ended->isEmpty()) {
            return;
        }

        // Unlinking ended the pair's teaching everywhere (department-wide too): bring all of it back.
        $pairs = $ended->map(fn (object $a): string => $a->teacher_id.':'.$a->subject_id)->unique()->all();
        $toRestore = DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)->where('status', 2)
            ->whereIn(DB::raw("teacher_id || ':' || subject_id"), $pairs)
            ->orderBy('id')
            ->get(['teacher_id', 'subject_id', 'branch_id', 'department_id', 'class_id', 'section_id']);

        foreach ($toRestore as $a) {
            $this->attempt(fn () => $this->handler(AssignTeacherSubjectHandler::class)->handle(new AssignTeacherSubjectCommand(
                $this->schoolId, (int) $a->teacher_id, (int) $a->subject_id, $this->yearId, $this->run.':restore-subject:'.$a->teacher_id.':'.$a->subject_id,
            )), 'restore subject');
            $this->attempt(fn () => $this->handler(AddTeachingAssignmentHandler::class)->handle(new AddTeachingAssignmentCommand(
                schoolId: $this->schoolId,
                academicYearId: $this->yearId,
                teacherId: (int) $a->teacher_id,
                subjectId: (int) $a->subject_id,
                branchId: (int) $a->branch_id,
                departmentId: $a->department_id !== null ? (int) $a->department_id : null,
                classId: $a->class_id !== null ? (int) $a->class_id : null,
                sectionId: $a->section_id !== null ? (int) $a->section_id : null,
                idempotencyKey: $this->run.':restore-teach:'.$a->teacher_id.':'.$a->subject_id.':'.($a->class_id ?? 0).':'.($a->section_id ?? 0),
            )), 'restore teaching');
        }
    }

    /** Takes every active lesson of the year off the grid (soft cancel) so the scenario starts clean. */
    private function clearGrid(): void
    {
        $this->bindSchool();
        $ids = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)->where('lifecycle_status', 1)
            ->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            $this->expect($this->handler(CancelScheduleHandler::class)->handle(new CancelScheduleCommand(
                $this->schoolId, (int) $id, $this->run.':clear:'.$id,
            )), 'clear lesson '.$id);
        }
    }

    private function autoPlace(int $sectionId): void
    {
        $this->expect($this->handler(AutoPlaceSectionHandler::class)->handle(new AutoPlaceSectionCommand(
            $this->schoolId, $this->yearId, [$sectionId], $this->run.':auto:'.$sectionId,
        )), 'auto place section '.$sectionId);
    }

    /** «استبدال»: the first two lessons of Sunday trade places (skipped when a teacher would clash). */
    private function swapFirstTwo(int $sectionId): void
    {
        // Theory lessons only — a swap must not break a practical double.
        $practical = $this->practicalSubjectIds();
        $lessons = array_values(array_filter($this->sectionLessons($sectionId), fn (object $o): bool => ! in_array((int) $o->subject_id, $practical, true)));
        foreach ($lessons as $i => $first) {
            foreach (array_slice($lessons, $i + 1) as $second) {
                if ((int) $first->subject_id === (int) $second->subject_id) {
                    continue;
                }
                try {
                    $result = $this->handler(SwapSchedulesHandler::class)->handle(new SwapSchedulesCommand(
                        $this->schoolId, (int) $first->id, (int) $second->id, $this->run.':swap:'.$first->id.':'.$second->id,
                    ));
                    if (! $result->failed()) {
                        return;
                    }
                } catch (SisDomainException) {
                    // A teacher would clash — try the next pair.
                }
            }
        }
        $this->command?->warn('TimetableScenarioSeeder: no pair of section '.$sectionId.' could swap.');
    }

    /** «زحف»: the day's first lesson slides one period later, pushing the packed day along (period 1 becomes free). */
    private function shiftDayLater(int $sectionId): void
    {
        // The day's last lesson (it alone moves), then the whole day from its first lesson.
        foreach ([5, 4, 3, 2, 1] as $dayOfWeek) {
            $day = $this->sectionDay($sectionId, $dayOfWeek);
            foreach ($day === [] ? [] : [end($day), $day[0]] as $index => $lesson) {
                try {
                    $result = $this->handler(ShiftScheduleHandler::class)->handle(new ShiftScheduleCommand(
                        $this->schoolId, $lesson->id, 1, $this->run.':shift:'.$sectionId.':'.$dayOfWeek.':'.$index,
                    ));
                    if (! $result->failed()) {
                        return;
                    }
                } catch (SisDomainException) {
                    // A teacher would clash — try the next lesson / day.
                }
            }
        }
        $this->command?->warn('TimetableScenarioSeeder: no day of section '.$sectionId.' could slide.');
    }

    /** Lessons taken off the grid (soft cancel) → they return to the tray. */
    private function cancelEvery(int $sectionId, int $every, string $tag): void
    {
        foreach ($this->sectionLessons($sectionId) as $index => $lesson) {
            if ($index % $every === 0) {
                $this->attempt(fn () => $this->handler(CancelScheduleHandler::class)->handle(new CancelScheduleCommand(
                    $this->schoolId, $lesson->id, $this->run.':cancel:'.$tag.':'.$lesson->id,
                )), 'cancel '.$lesson->id);
            }
        }
    }

    /** One subject gets an extra lesson, and lands a third time on the same day. */
    private function overPlace(int $sectionId): void
    {
        // Only a lesson already complete for the week — the extra one is then truly over-placed.
        $weekly = [];
        foreach ($this->handler(TimetableBoardLoader::class)->load($this->schoolId, $this->yearId)->requirements as $r) {
            if ($r['section_id'] === $sectionId) {
                $weekly[$r['subject_id'].':'.$r['teacher_id']] = $r['weekly'];
            }
        }
        $placed = [];
        $byDay = [];
        foreach ($this->sectionLessons($sectionId) as $lesson) {
            $placed[$lesson->subject_id.':'.$lesson->teacher_id] = ($placed[$lesson->subject_id.':'.$lesson->teacher_id] ?? 0) + 1;
            $byDay[$lesson->day_of_week.':'.$lesson->subject_id.':'.$lesson->teacher_id][] = $lesson;
        }
        uasort($byDay, static fn (array $a, array $b): int => count($b) <=> count($a));
        $practical = $this->practicalSubjectIds();
        foreach ($byDay as $same) {
            if (in_array((int) $same[0]->subject_id, $practical, true)) {
                continue;
            }
            $pair = $same[0]->subject_id.':'.$same[0]->teacher_id;
            if (($placed[$pair] ?? 0) < ($weekly[$pair] ?? PHP_INT_MAX)) {
                continue;
            }
            // All or nothing: only a day with enough free cells for the teacher (no stray extra lessons elsewhere).
            $need = 3 - count($same);
            if ($need > 0 && count($this->freeCells($sectionId, (int) $same[0]->teacher_id, (int) $same[0]->day_of_week)) >= $need) {
                $this->placeOnFreeCells($sectionId, (int) $same[0]->subject_id, (int) $same[0]->teacher_id, (int) $same[0]->day_of_week, $need, 'over');

                return;
            }
        }
    }

    /** A hole inside a day, and one half of a practical double taken away. */
    private function holeAndSplit(int $sectionId): void
    {
        $day = $this->sectionDay($sectionId, 2);
        if (count($day) >= 3) {
            $middle = $day[1];
            $this->attempt(fn () => $this->handler(CancelScheduleHandler::class)->handle(new CancelScheduleCommand(
                $this->schoolId, $middle->id, $this->run.':hole:'.$middle->id,
            )), 'hole '.$middle->id);
        }

        // The split: section 9 first, else the next sections that teach a practical double.
        $candidates = array_values(array_unique([$sectionId, $this->sections[7]['id'], $this->sections[6]['id'], $this->sections[5]['id']]));
        foreach ($candidates as $candidate) {
            if ($this->splitPractical($candidate)) {
                return;
            }
        }
    }

    /** The second half of a practical double moves to a later, non-adjacent cell of the same day. */
    private function splitPractical(int $sectionId): bool
    {
        $practical = $this->practicalSubjectIds();
        $position = array_flip($this->lessonPeriods);
        foreach ($this->sectionLessons($sectionId) as $lesson) {
            if (! in_array((int) $lesson->subject_id, $practical, true) || (int) $lesson->day_of_week === 2) {
                continue;
            }
            $day = $this->sectionDay($sectionId, (int) $lesson->day_of_week);
            $mate = array_values(array_filter($day, fn (object $o): bool => (int) $o->subject_id === (int) $lesson->subject_id && (int) $o->id !== (int) $lesson->id))[0] ?? null;
            if ($mate === null) {
                continue;
            }
            // Free a cell two or more periods after the double, then move the second half there.
            $target = null;
            foreach ($this->lessonPeriods as $periodId) {
                if ($position[$periodId] >= $position[(int) $mate->period_id] + 2 && ! in_array($periodId, array_map(fn (object $o): int => (int) $o->period_id, $day), true)) {
                    $target = $periodId;
                    break;
                }
            }
            if ($target === null && count($day) > 2) {
                $last = end($day);
                if ($position[(int) $last->period_id] >= $position[(int) $mate->period_id] + 2) {
                    $this->attempt(fn () => $this->handler(CancelScheduleHandler::class)->handle(new CancelScheduleCommand(
                        $this->schoolId, (int) $last->id, $this->run.':split-room:'.$last->id,
                    )), 'split room');
                    $target = (int) $last->period_id;
                }
            }
            if ($target === null) {
                continue;
            }
            $this->attempt(fn () => $this->handler(CancelScheduleHandler::class)->handle(new CancelScheduleCommand(
                $this->schoolId, (int) $mate->id, $this->run.':split:'.$mate->id,
            )), 'split '.$mate->id);
            if ($this->placeOnFreeCells($sectionId, (int) $mate->subject_id, (int) $mate->teacher_id, (int) $mate->day_of_week, 1, 'split-'.$target, $target) > 0) {
                return true;
            }
        }

        return false;
    }

    /** The busiest teacher gets lessons in the empty section on one day until they pass 6. */
    private function overloadTeacherDay(int $emptySectionId, int $dayOfWeek): void
    {
        $this->bindSchool();
        $busiest = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)->where('lifecycle_status', 1)
            ->where('day_of_week', $dayOfWeek)
            ->groupBy('teacher_id', 'subject_id')
            ->orderByRaw('COUNT(*) DESC')->orderBy('teacher_id')
            ->first(['teacher_id', 'subject_id', DB::raw('COUNT(*) as n')]);
        if ($busiest === null) {
            return;
        }
        $already = (int) DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)->where('lifecycle_status', 1)
            ->where('day_of_week', $dayOfWeek)->where('teacher_id', $busiest->teacher_id)->count();
        $this->placeOnFreeCells($emptySectionId, (int) $busiest->subject_id, (int) $busiest->teacher_id, $dayOfWeek, max(1, 7 - $already), 'overload');
    }

    /** One teacher stops teaching a subject (teachers page) — the lessons stay and the audit flags them. */
    private function orphanOneSubject(int $sectionId): void
    {
        // Not a subject the other scenarios rely on (sections 8 and 10).
        [$s8, $s10] = [$this->sections[7]['id'], $this->sections[9]['id']];
        $elsewhere = [];
        foreach ([$s8, $s10] as $other) {
            foreach ($this->sectionLessons($other) as $o) {
                $elsewhere[$o->teacher_id.':'.$o->subject_id] = true;
            }
        }
        $lesson = null;
        foreach ($this->sectionLessons($sectionId) as $candidate) {
            if (! isset($elsewhere[$candidate->teacher_id.':'.$candidate->subject_id])) {
                $lesson = $candidate;
                break;
            }
        }
        if ($lesson === null) {
            return;
        }
        $this->expect($this->handler(UnlinkTeacherSubjectHandler::class)->handle(new UnlinkTeacherSubjectCommand(
            $this->schoolId, (int) $lesson->teacher_id, (int) $lesson->subject_id, $this->yearId,
        )), 'unlink subject');
    }

    /** Places `count` lessons of subject × teacher on free cells of the day (section and teacher free). */
    private function placeOnFreeCells(int $sectionId, int $subjectId, int $teacherId, int $dayOfWeek, int $count, string $tag, ?int $onlyPeriod = null): int
    {
        $placed = 0;
        foreach ($onlyPeriod === null ? $this->lessonPeriods : [$onlyPeriod] as $periodId) {
            if ($count <= 0) {
                break;
            }
            $this->bindSchool();
            $taken = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
                ->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)->where('lifecycle_status', 1)
                ->where('day_of_week', $dayOfWeek)->where('period_id', $periodId)
                ->where(fn ($q) => $q->where('section_id', $sectionId)->orWhere('teacher_id', $teacherId))
                ->exists();
            if ($taken) {
                continue;
            }
            $ok = $this->attempt(fn () => $this->handler(CreateScheduleHandler::class)->handle(new CreateScheduleCommand(
                schoolId: $this->schoolId,
                sectionId: $sectionId,
                academicYearId: $this->yearId,
                dayOfWeek: $dayOfWeek,
                periodId: $periodId,
                subjectId: $subjectId,
                teacherId: $teacherId,
                idempotencyKey: $this->run.':'.$tag.':'.$sectionId.':'.$dayOfWeek.':'.$periodId,
            )), $tag.' lesson');
            if ($ok) {
                $count--;
                $placed++;
            }
        }

        return $placed;
    }

    /** @return list<int> subjects of type «عملي» */
    private function practicalSubjectIds(): array
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->where('subject_type', 3)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return list<int> lesson periods of the day free for both the section and the teacher */
    private function freeCells(int $sectionId, int $teacherId, int $dayOfWeek): array
    {
        $this->bindSchool();
        $taken = DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)->where('lifecycle_status', 1)
            ->where('day_of_week', $dayOfWeek)
            ->where(fn ($q) => $q->where('section_id', $sectionId)->orWhere('teacher_id', $teacherId))
            ->pluck('period_id')->map(fn ($id): int => (int) $id)->all();

        return array_values(array_diff($this->lessonPeriods, $taken));
    }

    /** @return list<object> the section's active lessons that day, in period order */
    private function sectionDay(int $sectionId, int $dayOfWeek): array
    {
        return array_values(array_filter($this->sectionLessons($sectionId), fn (object $s): bool => (int) $s->day_of_week === $dayOfWeek));
    }

    /** @return list<object> the section's active lessons by day and period order */
    private function sectionLessons(int $sectionId): array
    {
        $this->bindSchool();

        return DB::table(SchemaHelper::qualified('timetable', 'schedules').' as s')
            ->join(SchemaHelper::qualified('timetable', 'periods').' as p', 'p.id', '=', 's.period_id')
            ->where('s.school_id', $this->schoolId)->where('s.academic_year_id', $this->yearId)
            ->where('s.section_id', $sectionId)->where('s.lifecycle_status', 1)
            ->orderBy('s.day_of_week')->orderBy('p.period_number')
            ->get(['s.id', 's.day_of_week', 's.period_id', 's.subject_id', 's.teacher_id'])
            ->all();
    }

    private function report(): void
    {
        $board = $this->handler(TimetableBoardLoader::class)->load($this->schoolId, $this->yearId);
        $issues = $this->handler(TimetableAuditor::class)->audit($board);
        $bySeverity = array_count_values(array_column($issues, 'severity'));
        $teachers = count(array_unique(array_column($board->schedules, 'teacher_id')));

        $this->command?->info(sprintf(
            'TimetableScenarioSeeder: %d lessons for %d teachers in %d sections · audit: %d errors, %d warnings, %d notes.',
            count($board->schedules),
            $teachers,
            count(array_unique(array_column($board->schedules, 'section_id'))),
            $bySeverity['error'] ?? 0,
            $bySeverity['warning'] ?? 0,
            $bySeverity['info'] ?? 0,
        ));
    }

    /** A scenario step that may legitimately be refused (a clash): log it and go on. */
    private function attempt(callable $step, string $what): bool
    {
        try {
            $result = $step();
            if ($result instanceof ApplicationResult && $result->failed()) {
                $this->command?->warn("TimetableScenarioSeeder: {$what} skipped — ".implode(', ', $result->errors));

                return false;
            }

            return true;
        } catch (SisDomainException $e) {
            $this->command?->warn("TimetableScenarioSeeder: {$what} skipped — ".$e->errorCode());

            return false;
        }
    }

    private function expect(ApplicationResult $result, string $what): void
    {
        if ($result->failed()) {
            throw new RuntimeException("TimetableScenarioSeeder: {$what} failed — ".implode(', ', $result->errors));
        }
    }

    private function bindSchool(): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->schoolId]);
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function handler(string $class): object
    {
        return app($class);
    }
}
