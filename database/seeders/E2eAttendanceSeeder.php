<?php

namespace Database\Seeders;

use App\Application\Attendance\Commands\DecideAttendanceExcuseCommand;
use App\Application\Attendance\Commands\DecideAttendanceExcuseHandler;
use App\Application\Attendance\Commands\DetectAttendanceAlertsCommand;
use App\Application\Attendance\Commands\DetectAttendanceAlertsHandler;
use App\Application\Attendance\Commands\OpenLessonAttendanceCommand;
use App\Application\Attendance\Commands\OpenLessonAttendanceHandler;
use App\Application\Attendance\Commands\RecordStaffAttendanceCommand;
use App\Application\Attendance\Commands\RecordStaffAttendanceHandler;
use App\Application\Attendance\Commands\ReviewAttendanceSheetCommand;
use App\Application\Attendance\Commands\ReviewAttendanceSheetHandler;
use App\Application\Attendance\Commands\SaveAttendanceSheetCommand;
use App\Application\Attendance\Commands\SaveAttendanceSheetHandler;
use App\Application\Attendance\Commands\SubmitAttendanceExcuseCommand;
use App\Application\Attendance\Commands\SubmitAttendanceExcuseHandler;
use App\Application\Attendance\Commands\SubmitAttendanceSheetCommand;
use App\Application\Attendance\Commands\SubmitAttendanceSheetHandler;
use App\Application\Attendance\Contracts\ApprovedLeaveSourceInterface;
use App\Application\Attendance\Contracts\LessonSourceInterface;
use App\Application\Attendance\Support\SheetActor;
use App\Database\SchemaHelper;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\Data\SheetMark;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use App\Domain\Attendance\ValueObjects\SheetAction;
use App\Domain\Attendance\ValueObjects\StaffAttendanceStatus;
use App\Domain\Shared\Exceptions\SisDomainException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Attendance of the e2e dataset for اعدادية التحرير — the school days of the last two weeks (today stays open for
 * the UI tests), through the sheet workflow's own handlers, acting as the administrator (reviewer):
 *
 * - every lesson of the timetable on each date: ~65 % approved, 10 % submitted, 5 % under review, 5 % returned,
 *   2 % rejected, 8 % saved as a draft, 5 % never opened (the «unmarked lessons» case);
 * - marks: absent ~5 %, late ~4 % (with minutes), left early ~1.5 %, the rest present; a few students are absent
 *   far more often, so the alert rules have something to find;
 * - excuses: submitted for some absences — approved (absences become excused), rejected and still pending;
 * - staff days: present / late / left early / absent / official mission / excused absence, and «approved leave»
 *   for every teacher the leave module has on approved leave that day; some days left unrecorded.
 *
 * Holidays and weekends are skipped by the handlers' own rules (counted). Deterministic: the outcome of every
 * lesson, student and teacher is a hash of the batch id and its ids — no random numbers.
 */
final class E2eAttendanceSeeder extends Seeder
{
    private const SCHOOL = 'اعدادية التحرير';

    private const DAYS_BACK = 16;

    /** @var array<string, mixed> */
    public array $summary = ['dates' => [], 'school_days' => 0, 'sheets' => [], 'marks' => [], 'excuses' => [], 'staff' => [], 'refused' => []];

    private string $batch = 'sis-e2e';

    private int $adminId = 0;

    private int $schoolId = 0;

    private int $yearId = 0;

    /** @var list<array{enrollment_id: int, date: string, session_id: int}> absences that may get an excuse */
    private array $absences = [];

    public function forBatch(string $batch, int $adminId): self
    {
        $this->batch = $batch;
        $this->adminId = $adminId;

        return $this;
    }

    public function run(): void
    {
        if ($this->adminId === 0) {
            throw new RuntimeException('E2eAttendanceSeeder needs the administrator persona (forBatch()).');
        }
        $this->schoolId = (int) (DB::table(SchemaHelper::qualified('organization', 'schools'))->where('name', self::SCHOOL)->value('id')
            ?? throw new RuntimeException('Missing school '.self::SCHOOL));
        $year = DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('is_current', true)->first(['id', 'start_date'])
            ?? throw new RuntimeException('No current academic year.');
        $this->yearId = (int) $year->id;
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->schoolId]);

        $today = CarbonImmutable::today();
        $from = CarbonImmutable::parse((string) $year->start_date)->max($today->subDays(self::DAYS_BACK));
        for ($date = $from; $date->lt($today); $date = $date->addDay()) {
            $day = $date->toDateString();
            $lessons = app(LessonSourceInterface::class)->lessonsOn($this->schoolId, $this->yearId, $day);
            if ($lessons === []) {
                continue;
            }
            if (app(AttendanceSheetRepositoryInterface::class)->isHoliday($this->schoolId, $this->yearId, $day)) {
                $this->count('refused', 'holiday '.$day);

                continue;
            }
            $this->summary['school_days']++;
            $this->summary['dates'][] = $day;
            foreach ($lessons as $lesson) {
                $this->lesson($lesson, $day);
            }
            $this->staffDay($day);
        }

        $this->excuses();
        $this->guarded('alerts', fn () => app(DetectAttendanceAlertsHandler::class)->handle(new DetectAttendanceAlertsCommand(
            $this->schoolId, $this->yearId, $today->subDay()->toDateString(), $this->batch.':att:alerts',
        )));

        $this->command?->info('E2eAttendanceSeeder: '.json_encode(array_diff_key($this->summary, ['dates' => true]), JSON_UNESCAPED_UNICODE));
    }

    private function lesson(LessonSlot $lesson, string $day): void
    {
        $roll = $this->roll('sheet', $day, $lesson->scheduleId) % 100;
        $outcome = match (true) {
            $roll < 65 => 'approved',
            $roll < 75 => 'submitted',
            $roll < 80 => 'under_review',
            $roll < 85 => 'returned',
            $roll < 87 => 'rejected',
            $roll < 95 => 'draft',
            default => 'not_opened',
        };
        if ($outcome === 'not_opened') {
            $this->count('sheets', $outcome);

            return;
        }
        $actor = new SheetActor($this->adminId, null, true);
        $key = $this->batch.':att:'.$day.':'.$lesson->scheduleId;

        $this->guarded('sheet', function () use ($lesson, $day, $actor, $key, $outcome): void {
            $opened = app(OpenLessonAttendanceHandler::class)->handle(new OpenLessonAttendanceCommand(
                $this->schoolId, $this->yearId, $lesson->scheduleId, $day, $actor, $key.':open',
            ));
            $sessionId = (int) $opened->get('session_id');
            $marks = $this->marks($lesson, $day, $sessionId, $outcome !== 'draft');
            $version = $this->version($sessionId);

            if ($outcome === 'draft') {
                app(SaveAttendanceSheetHandler::class)->handle(new SaveAttendanceSheetCommand(
                    $this->schoolId, $this->yearId, $sessionId, $version, $marks, $actor, $key.':save',
                ));
                $this->count('sheets', $outcome);

                return;
            }
            $submitted = app(SubmitAttendanceSheetHandler::class)->handle(new SubmitAttendanceSheetCommand(
                $this->schoolId, $this->yearId, $sessionId, $version, $marks, $actor, $key.':submit',
            ));
            $version = (int) $submitted->get('version');
            $review = fn (SheetAction $action, ?string $reason): int => (int) app(ReviewAttendanceSheetHandler::class)->handle(new ReviewAttendanceSheetCommand(
                $this->schoolId, $this->yearId, $sessionId, $version, $action, $reason, $this->adminId, $key.':'.$action->value,
            ))->get('version');
            match ($outcome) {
                'approved' => $review(SheetAction::Approve, null),
                'under_review' => $review(SheetAction::StartReview, null),
                'returned' => $review(SheetAction::Return, 'يرجى مراجعة حالات التأخير قبل الاعتماد'),
                'rejected' => $review(SheetAction::Reject, 'السجل لا يطابق دفتر الخفارة'),
                default => null,
            };
            $this->count('sheets', $outcome);
        });
    }

    /** @return list<SheetMark> the exceptions only — submit records everyone else present */
    private function marks(LessonSlot $lesson, string $day, int $sessionId, bool $remember): array
    {
        $roster = app(AttendanceSheetRepositoryInterface::class)->roster($this->schoolId, $this->yearId, $lesson->sectionId, $lesson->groupId, $day);
        $marks = [];
        foreach ($roster as $entry) {
            $roll = $this->roll('mark', $day, $lesson->scheduleId, $entry->enrollmentId) % 1000;
            // A few students are absent far more often (alert rules).
            $chronic = $entry->enrollmentId % 37 === 0 && $this->roll('chronic', $day, $entry->enrollmentId) % 100 < 45;
            $mark = match (true) {
                $chronic || $roll < 50 => new SheetMark($entry->enrollmentId, AttendanceRecordStatus::Absent->value),
                $roll < 90 => new SheetMark($entry->enrollmentId, AttendanceRecordStatus::Late->value, 5 + $roll % 21),
                $roll < 105 => new SheetMark($entry->enrollmentId, AttendanceRecordStatus::LeftEarly->value, null, 'مغادرة بإذن ولي الأمر'),
                default => null,
            };
            if ($mark === null) {
                continue;
            }
            $marks[] = $mark;
            $this->count('marks', AttendanceRecordStatus::from($mark->status)->name);
            if ($remember && $mark->status === AttendanceRecordStatus::Absent->value) {
                $this->absences[] = ['enrollment_id' => $entry->enrollmentId, 'date' => $day, 'session_id' => $sessionId];
            }
        }

        return $marks;
    }

    /** Excuses for some absences: approved (the absence becomes excused), rejected, still pending. */
    private function excuses(): void
    {
        $seen = [];
        $picked = [];
        foreach ($this->absences as $absence) {
            $k = $absence['enrollment_id'].'|'.$absence['date'];
            if (isset($seen[$k]) || $this->roll('excuse', $absence['date'], $absence['enrollment_id']) % 6 !== 0) {
                continue;
            }
            $seen[$k] = true;
            $picked[] = $absence;
            if (count($picked) === 12) {
                break;
            }
        }
        foreach ($picked as $i => $absence) {
            $this->guarded('excuse', function () use ($i, $absence): void {
                $key = $this->batch.':att:excuse:'.$absence['enrollment_id'].':'.$absence['date'];
                $submitted = app(SubmitAttendanceExcuseHandler::class)->handle(new SubmitAttendanceExcuseCommand(
                    $this->schoolId, $this->yearId, $absence['enrollment_id'], $absence['date'], $absence['date'], null,
                    1 + $i % 9, ['مراجعة طبية', 'ظرف عائلي', 'مراجعة دائرة رسمية'][$i % 3], null, $this->adminId, $key,
                ));
                $excuseId = (int) $submitted->get('excuse_id');
                $decision = $i % 4;
                if ($decision === 3 || $excuseId === 0) {
                    $this->count('excuses', 'pending');

                    return;
                }
                $version = (int) DB::table(SchemaHelper::qualified('attendance', 'excuses'))->where('id', $excuseId)->value('version');
                app(DecideAttendanceExcuseHandler::class)->handle(new DecideAttendanceExcuseCommand(
                    $this->schoolId, $this->yearId, $excuseId, $version, $decision !== 1,
                    $decision === 1 ? 'لا توجد وثيقة تثبت السبب' : null, $this->adminId, $key.':decide',
                ));
                $this->count('excuses', $decision === 1 ? 'rejected' : 'approved');
            });
        }
    }

    private function staffDay(string $day): void
    {
        $onLeave = app(ApprovedLeaveSourceInterface::class)->teachersOnApprovedLeave($this->schoolId, $day);
        $teachers = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools').' as ts')
            ->join(SchemaHelper::qualified('teachers', 'teachers').' as t', 't.id', '=', 'ts.teacher_id')
            ->where('ts.school_id', $this->schoolId)->where('ts.academic_year_id', $this->yearId)->whereNull('ts.left_at')->where('t.status', 1)
            ->orderBy('t.id')->pluck('t.id');
        foreach ($teachers as $teacherId) {
            $teacherId = (int) $teacherId;
            $roll = $this->roll('staff', $day, $teacherId) % 1000;
            [$status, $in, $out, $late] = match (true) {
                isset($onLeave[$teacherId]) => [StaffAttendanceStatus::ApprovedLeave, null, null, null],
                $roll < 870 => [StaffAttendanceStatus::Present, sprintf('07:%02d', 40 + $roll % 20), '13:30', null],
                $roll < 920 => [StaffAttendanceStatus::Late, sprintf('08:%02d', 5 + $roll % 25), '13:30', 5 + $roll % 25],
                $roll < 940 => [StaffAttendanceStatus::LeftEarly, '07:50', '11:00', null],
                $roll < 955 => [StaffAttendanceStatus::Absent, null, null, null],
                $roll < 965 => [StaffAttendanceStatus::OfficialMission, null, null, null],
                $roll < 975 => [StaffAttendanceStatus::ExcusedAbsence, null, null, null],
                default => [null, null, null, null],
            };
            if ($status === null) {
                $this->count('staff', 'not_recorded');

                continue;
            }
            $this->guarded('staff', function () use ($teacherId, $day, $status, $in, $out, $late): void {
                app(RecordStaffAttendanceHandler::class)->handle(new RecordStaffAttendanceCommand(
                    $this->schoolId, $this->yearId, $teacherId, $day, $status->value, $in, $out, $late,
                    null, null, null, $this->adminId, $this->batch.':att:staff:'.$day.':'.$teacherId,
                ));
                $this->count('staff', $status->name);
            });
        }
    }

    private function version(int $sessionId): int
    {
        return (int) DB::table(SchemaHelper::qualified('attendance', 'sessions'))->where('id', $sessionId)->value('version');
    }

    /** A refusal by the rules is counted (and shown in the report), never hidden. */
    private function guarded(string $what, callable $work): void
    {
        try {
            $work();
        } catch (SisDomainException $e) {
            $this->count('refused', $what.': '.$e->errorCode());
        }
    }

    private function roll(string ...$parts): int
    {
        return crc32($this->batch.'|'.implode('|', $parts));
    }

    private function count(string $bucket, string $key): void
    {
        $this->summary[$bucket][$key] = ($this->summary[$bucket][$key] ?? 0) + 1;
    }
}
