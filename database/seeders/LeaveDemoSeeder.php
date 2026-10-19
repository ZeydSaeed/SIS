<?php

namespace Database\Seeders;

use App\Application\Academic\Commands\ChangeHolidayStatusCommand;
use App\Application\Academic\Commands\ChangeHolidayStatusHandler;
use App\Application\Academic\Commands\SaveHolidayCommand;
use App\Application\Academic\Commands\SaveHolidayHandler;
use App\Application\Leave\Commands\AddSuggestedLeaveTypesCommand;
use App\Application\Leave\Commands\AddSuggestedLeaveTypesHandler;
use App\Application\Leave\Commands\AttachLeaveDocumentCommand;
use App\Application\Leave\Commands\AttachLeaveDocumentHandler;
use App\Application\Leave\Commands\CancelLeaveRequestCommand;
use App\Application\Leave\Commands\CancelLeaveRequestHandler;
use App\Application\Leave\Commands\DecideLeaveRequestCommand;
use App\Application\Leave\Commands\DecideLeaveRequestHandler;
use App\Application\Leave\Commands\GrantLeaveEntitlementsCommand;
use App\Application\Leave\Commands\GrantLeaveEntitlementsHandler;
use App\Application\Leave\Commands\PublishLeavePolicyCommand;
use App\Application\Leave\Commands\PublishLeavePolicyHandler;
use App\Application\Leave\Commands\SaveLeaveRequestCommand;
use App\Application\Leave\Commands\SaveLeaveRequestHandler;
use App\Application\Leave\Commands\SubmitLeaveRequestCommand;
use App\Application\Leave\Commands\SubmitLeaveRequestHandler;
use App\Application\Leave\Commands\WithdrawLeaveRequestCommand;
use App\Application\Leave\Commands\WithdrawLeaveRequestHandler;
use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\ValueObjects\DayBasis;
use App\Domain\Leave\ValueObjects\LeaveDecision;
use App\Domain\Leave\ValueObjects\RequesterKind;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo data for «الإجازات والعطل»: about a hundred leave requests spread over every type, status and approval path, a
 * few holidays and some staff. It goes through the module's own command handlers (so every rule, balance entry, history
 * row and notification is the real thing); refusals the rules make on purpose are counted, not hidden.
 *
 *   php artisan db:seed --class=LeaveDemoSeeder
 *
 * Skipped when the school already holds leave requests (`LEAVE_DEMO_FORCE=1` adds another batch).
 */
class LeaveDemoSeeder extends Seeder
{
    private const REQUESTS = 100;

    private const STAFF_TEACHERS = 36;

    /** A tiny valid-looking PDF (the Documents module checks size and MIME, not the content). */
    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";

    private int $schoolId;

    private int $yearId;

    private int $manager;

    /** @var list<int> */
    private array $approvers = [];

    private string $run;

    private int $seq = 0;

    /** @var array<string, int> */
    private array $refused = [];

    /** @var array<string, int> */
    private array $made = [];

    public function run(): void
    {
        $this->schoolId = (int) (DB::table('organization.schools')->orderBy('id')->value('id') ?? 0);
        $this->yearId = (int) (DB::table('academic.academic_years')->where('is_current', true)->value('id') ?? 0);
        if ($this->schoolId === 0 || $this->yearId === 0) {
            $this->say('No school or current academic year: nothing seeded.');

            return;
        }
        // Row level security reads the school from the session — the same setting a request carries.
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->schoolId]);
        if (DB::table('leave.requests')->where('school_id', $this->schoolId)->exists() && ! env('LEAVE_DEMO_FORCE')) {
            $this->say('The school already has leave requests: skipped (LEAVE_DEMO_FORCE=1 adds another batch).');

            return;
        }
        $this->run = Str::lower(Str::random(6));

        $this->manager = (int) (User::query()->orderBy('id')->value('id') ?? 0);
        $this->approvers = $this->approverUsers();
        $people = $this->staff();
        $types = $this->types();
        $this->holidays();
        $this->requests($people, $types);
        $this->negatives($people[0], $types);

        $this->say('Seeded: '.json_encode($this->made, JSON_UNESCAPED_UNICODE));
        $this->say('Refused by the rules (expected): '.json_encode($this->refused, JSON_UNESCAPED_UNICODE));
    }

    /** @return list<int> two demo approvers (holders of the approve role) — never the requester. */
    private function approverUsers(): array
    {
        $roles = app(SecurityPermissionSeeder::class);
        $roles->run();
        $ids = [];
        foreach (['leave-demo-1@sis.test' => 'معتمد الإجازات الأول', 'leave-demo-2@sis.test' => 'معتمد الإجازات الثاني'] as $email => $name) {
            $user = User::query()->where('email', $email)->first() ?? User::query()->create([
                'name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(40)), 'email_verified_at' => now(),
            ]);
            $roles->assignRole($user, 'leave_approver', $this->schoolId);
            $ids[] = (int) $user->id;
        }
        // The first user (the school's administrator) may approve as well, but never their own leave.
        $roles->assignRole(User::query()->findOrFail($this->manager), 'leave_approver', $this->schoolId);

        return $ids;
    }

    /** @return list<array{0: RequesterKind, 1: int}> */
    private function staff(): array
    {
        $people = [];
        $teachers = DB::table('teachers.teachers as t')->join('teachers.teacher_schools as ts', 'ts.teacher_id', '=', 't.id')
            ->where('ts.school_id', $this->schoolId)->where('ts.academic_year_id', $this->yearId)->whereNull('ts.left_at')
            ->orderBy('t.id')->limit(self::STAFF_TEACHERS)->pluck('t.id');
        foreach ($teachers as $id) {
            $people[] = [RequesterKind::Teacher, (int) $id];
        }
        // Administrative employees (the school may have none yet).
        $have = DB::table('hr.employee_schools as es')->join('hr.employees as e', 'e.id', '=', 'es.employee_id')
            ->where('es.school_id', $this->schoolId)->where('es.academic_year_id', $this->yearId)->whereNull('e.teacher_id')->where('e.status', 1)->pluck('e.id');
        foreach ($have as $id) {
            $people[] = [RequesterKind::Employee, (int) $id];
        }
        for ($i = $have->count() + 1; $i <= 6; $i++) {
            $id = (int) DB::table('hr.employees')->insertGetId([
                'employee_number' => 'LV-DEMO-'.$i, 'first_name' => 'موظف', 'last_name' => (string) $i, 'full_name' => 'موظف إداري '.$i,
                'status' => 1, 'effective_from' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('hr.employee_schools')->insert(['employee_id' => $id, 'school_id' => $this->schoolId, 'academic_year_id' => $this->yearId, 'is_primary' => true, 'created_at' => now()]);
            $people[] = [RequesterKind::Employee, $id];
        }
        $this->made['staff'] = count($people);

        return $people;
    }

    /** @return array<string, int> type code => id */
    private function types(): array
    {
        app(AddSuggestedLeaveTypesHandler::class)->handle(new AddSuggestedLeaveTypesCommand($this->schoolId, $this->manager, $this->key()));
        $ids = DB::table('leave.types')->where('school_id', $this->schoolId)->pluck('id', 'code')->map(fn ($v): int => (int) $v)->all();
        // Policies a school would set: entitlements, limits and approval levels (the suggested ones carry none).
        $policy = fn (string $code, DayBasis $basis, bool $doc, bool $deducts, int $levels, ?float $max, ?float $entitled) => isset($ids[$code])
            ? app(PublishLeavePolicyHandler::class)->handle(new PublishLeavePolicyCommand($this->schoolId, $ids[$code], new LeavePolicyTerms($basis, $doc, $deducts, $levels, $max, $entitled), $this->manager, $this->key()))
            : null;
        $policy('ANNUAL', DayBasis::WorkingDays, false, true, 2, 15, 24);
        $policy('EMERGENCY', DayBasis::WorkingDays, false, true, 1, 3, 6);
        $policy('HOURLY', DayBasis::WorkingDays, false, true, 1, 4, 40);
        $policy('SICK', DayBasis::CalendarDays, true, false, 1, 30, null);
        $policy('MATERNITY', DayBasis::CalendarDays, true, false, 2, 98, null);
        foreach (['ANNUAL', 'EMERGENCY', 'HOURLY'] as $code) {
            app(GrantLeaveEntitlementsHandler::class)->handle(new GrantLeaveEntitlementsCommand($this->schoolId, $this->yearId, $ids[$code], $this->manager, $this->key()));
        }

        return $ids;
    }

    private function holidays(): void
    {
        $y = (int) substr((string) DB::table('academic.academic_years')->where('id', $this->yearId)->value('start_date'), 0, 4);
        DB::table('academic.holidays')->insert([
            ['academic_year_id' => $this->yearId, 'school_id' => null, 'name' => 'رأس السنة الميلادية', 'start_date' => ($y + 1).'-01-01', 'end_date' => ($y + 1).'-01-01', 'holiday_type' => 1, 'created_at' => now(), 'status' => 1],
            ['academic_year_id' => $this->yearId, 'school_id' => null, 'name' => 'عيد الجيش', 'start_date' => ($y + 1).'-01-06', 'end_date' => ($y + 1).'-01-06', 'holiday_type' => 1, 'created_at' => now(), 'status' => 1],
            ['academic_year_id' => $this->yearId, 'school_id' => null, 'name' => 'عيد العمال', 'start_date' => ($y + 1).'-05-01', 'end_date' => ($y + 1).'-05-01', 'holiday_type' => 1, 'created_at' => now(), 'status' => 1],
        ]);
        $own = [
            ['عطلة منتصف الفصل الأول', $y.'-11-01', $y.'-11-03', 2],
            ['انقطاع بين الفصلين', ($y + 1).'-01-20', ($y + 1).'-02-03', 3],
            ['يوم رياضي', ($y + 1).'-03-10', ($y + 1).'-03-10', 2],
            ['عطلة الربيع', ($y + 1).'-04-04', ($y + 1).'-04-08', 2],
            ['مناسبة ملغاة', ($y + 1).'-05-20', ($y + 1).'-05-20', 2],
        ];
        $ids = [];
        foreach ($own as [$name, $from, $to, $type]) {
            $r = app(SaveHolidayHandler::class)->handle(new SaveHolidayCommand($this->schoolId, null, $this->yearId, $name, $from, $to, $type, $this->key()));
            $ids[] = (int) $r->id;
        }
        // One holiday taken out of the calendar (kept, not deleted).
        app(ChangeHolidayStatusHandler::class)->handle(new ChangeHolidayStatusCommand($this->schoolId, end($ids), false, $this->key()));
        $this->made['holidays'] = count($own) + 3;
    }

    /**
     * @param  list<array{0: RequesterKind, 1: int}>  $people
     * @param  array<string, int>  $types
     */
    private function requests(array $people, array $types): void
    {
        $codes = ['ANNUAL', 'ANNUAL', 'EMERGENCY', 'SICK', 'UNPAID', 'MATERNITY', 'OFFICIAL_MISSION', 'HOURLY', 'ANNUAL', 'SICK'];
        $start = new \DateTimeImmutable((string) DB::table('academic.academic_years')->where('id', $this->yearId)->value('start_date'));
        // Days that are not working days (weekend, holiday) are refused by the rules: go on until the hundredth stands.
        for ($i = 0; ($this->made['requests'] ?? 0) < self::REQUESTS && $i < self::REQUESTS * 2; $i++) {
            [$kind, $person] = $people[$i % count($people)];
            $round = intdiv($i, count($people));
            $code = $codes[$i % count($codes)];
            $hours = $code === 'HOURLY';
            // Sunday-aligned starts, a different week per person and round, so one person's requests never overlap.
            $from = $start->modify('+'.(14 + (($i % count($people)) * 5 + $round * 97) % 240).' days');
            $from = $from->modify('-'.$from->format('w').' days')->modify('+'.(($i % 3)).' days');
            $length = $hours ? 1 : 1 + ($i % 5);
            $to = $from->modify('+'.($length - 1).' days');
            $draft = new LeaveDraft($types[$code] ?? 0, $from->format('Y-m-d'), $to->format('Y-m-d'), $hours ? 1.5 + ($i % 3) * 0.5 : null, $this->reason($code, $i));
            $id = $this->attempt(fn () => (int) app(SaveLeaveRequestHandler::class)->handle(new SaveLeaveRequestCommand(
                $this->schoolId, $this->yearId, null, null, $kind, $person, $draft, $this->manager, true, $this->key(),
            ))->get('request_id'));
            if ($id === null) {
                continue;
            }
            $this->made['requests'] = ($this->made['requests'] ?? 0) + 1;
            $this->finish($id, $i, $code);
        }
    }

    /**
     * Requests that MUST be refused, one for each rule a user can run into — so the dialogs and the codes get exercised
     * on real data (each refusal is counted under its code in the summary).
     *
     * @param  array{0: RequesterKind, 1: int}  $who
     * @param  array<string, int>  $types
     */
    private function negatives(array $who, array $types): void
    {
        [$kind, $person] = $who;
        $year = new \DateTimeImmutable((string) DB::table('academic.academic_years')->where('id', $this->yearId)->value('start_date'));
        $day = fn (int $n): string => $year->modify('+'.$n.' days')->format('Y-m-d');
        $save = fn (string $code, string $from, string $to, ?float $hours = 1.0, string $reason = 'اختبار', bool $manage = true, ?int $actor = null) => $this->attempt(fn () => (int) app(SaveLeaveRequestHandler::class)->handle(new SaveLeaveRequestCommand(
            $this->schoolId, $this->yearId, null, null, $kind, $person, new LeaveDraft($types[$code], $from, $to, $hours, $reason), $actor ?? $this->manager, $manage, $this->key(),
        ))->get('request_id'));
        $submit = fn (int $id) => $this->attempt(fn () => app(SubmitLeaveRequestHandler::class)->handle(new SubmitLeaveRequestCommand($this->schoolId, $id, $this->version($id), $this->manager, true, $this->key())));

        $save('ANNUAL', $day(40), $day(30), null);                  // dates_invalid
        $save('ANNUAL', $day(-20), $day(-19), null);                 // outside_year
        $save('ANNUAL', $day(40), $day(41), null, '   ');             // reason_invalid
        $save('HOURLY', $day(42), $day(43), 2.0);                    // hours_one_day
        $save('HOURLY', $day(42), $day(42), 2.1);                    // hours_invalid
        $save('ANNUAL', $day(60), $day(100), null);                  // exceeds_max (15 per request)
        $save('ANNUAL', $day(40), $day(41), null, 'ok', false, $this->approvers[0]); // not_own_request
        if (($id = $save('SICK', $day(230), $day(232), null)) !== null) {
            $submit($id);                                           // document_required
        }
        $first = $save('EMERGENCY', $day(200), $day(202), null);
        $second = $save('EMERGENCY', $day(201), $day(203), null);
        $third = $save('EMERGENCY', $day(210), $day(212), null);
        $fourth = $save('EMERGENCY', $day(215), $day(217), null);
        foreach ([$first, $second, $third, $fourth] as $id) {
            if ($id !== null) {
                $submit($id);                                       // overlap (2nd) · balance_insufficient (4th: 6 hold back 3+3)
            }
        }
        if ($first !== null) {
            $stale = $this->version($first) - 1;
            $this->attempt(fn () => app(DecideLeaveRequestHandler::class)->handle(new DecideLeaveRequestCommand($this->schoolId, $first, $stale, LeaveDecision::Approve, null, $this->approvers[0], $this->key()))); // version_conflict
            $this->attempt(fn () => app(DecideLeaveRequestHandler::class)->handle(new DecideLeaveRequestCommand($this->schoolId, $first, $this->version($first), LeaveDecision::Reject, '', $this->approvers[0], $this->key()))); // note_required
            $this->attempt(fn () => app(CancelLeaveRequestHandler::class)->handle(new CancelLeaveRequestCommand($this->schoolId, $first, $this->version($first), 'x', $this->manager, $this->key()))); // transition_invalid (still pending)
        }
        $this->attempt(fn () => app(SubmitLeaveRequestHandler::class)->handle(new SubmitLeaveRequestCommand($this->schoolId, 999999999, 1, $this->manager, true, $this->key()))); // request_missing
    }

    /** Takes one request along the path its index selects: draft, pending, approved, rejected, returned, withdrawn, cancelled. */
    private function finish(int $id, int $i, string $code): void
    {
        $stage = $i % 10;
        if ($stage <= 1) {
            $this->made['draft'] = ($this->made['draft'] ?? 0) + 1;

            return;
        }
        if (in_array($code, ['SICK', 'MATERNITY'], true) && $stage !== 7) {
            $this->attempt(fn () => app(AttachLeaveDocumentHandler::class)->handle(new AttachLeaveDocumentCommand(
                $this->schoolId, $id, ['name' => 'report.pdf', 'mime' => 'application/pdf', 'contents' => self::PDF], $this->manager, true, $this->key(),
            )));
        }
        // Stage 7 on a document-requiring type is submitted WITHOUT its file on purpose: the rule must refuse it.
        if ($this->attempt(fn () => app(SubmitLeaveRequestHandler::class)->handle(new SubmitLeaveRequestCommand($this->schoolId, $id, $this->version($id), $this->manager, true, $this->key()))) === null) {
            return;
        }
        $this->bump('submitted');
        $approver = $this->approvers[$i % count($this->approvers)];
        match (true) {
            $stage <= 3 => null, // left pending
            $stage === 4 => $this->decide($id, LeaveDecision::Approve, $approver, null), // first level only (when the policy has two)
            $stage <= 6 => $this->approveAll($id, $i),
            $stage === 7 => $this->decide($id, LeaveDecision::Reject, $approver, 'لا تسمح ظروف الدوام بهذه الإجازة في هذا الأسبوع'),
            $stage === 8 => $this->decide($id, LeaveDecision::RequestInfo, $approver, 'يرجى توضيح السبب وإرفاق ما يلزم'),
            default => $this->attempt(fn () => app(WithdrawLeaveRequestHandler::class)->handle(new WithdrawLeaveRequestCommand($this->schoolId, $id, $this->version($id), 'تغيّر الموعد', $this->manager, true, $this->key()))),
        };
        // Some approved leave is cancelled afterwards: the deducted balance must come back.
        if ($stage >= 5 && $stage <= 6 && $i % 17 === 0) {
            $this->attempt(fn () => app(CancelLeaveRequestHandler::class)->handle(new CancelLeaveRequestCommand($this->schoolId, $id, $this->version($id), 'لم تُستخدم الإجازة', $this->manager, $this->key())));
            $this->bump('cancelled');
        }
    }

    private function approveAll(int $id, int $i): void
    {
        $levels = (int) DB::table('leave.requests')->where('id', $id)->value('approval_levels');
        for ($level = 0; $level < $levels; $level++) {
            // A different approver for each level (the rules demand it).
            $this->decide($id, LeaveDecision::Approve, $this->approvers[($i + $level) % count($this->approvers)], null);
        }
    }

    private function decide(int $id, LeaveDecision $decision, int $actor, ?string $note): void
    {
        $this->attempt(fn () => app(DecideLeaveRequestHandler::class)->handle(new DecideLeaveRequestCommand($this->schoolId, $id, $this->version($id), $decision, $note, $actor, $this->key())));
        $this->bump($decision->value);
    }

    /** Runs one command; a refusal by the rules is counted by its code and returns null. */
    private function attempt(callable $call): mixed
    {
        try {
            return $call() ?? true;
        } catch (LeaveRuleViolation $e) {
            $this->refused[$e->errorCode()] = ($this->refused[$e->errorCode()] ?? 0) + 1;

            return null;
        }
    }

    private function bump(string $key): void
    {
        $this->made[$key] = ($this->made[$key] ?? 0) + 1;
    }

    private function version(int $id): int
    {
        return (int) DB::table('leave.requests')->where('id', $id)->value('version');
    }

    private function key(): string
    {
        return 'leave-demo-'.$this->run.'-'.(++$this->seq);
    }

    private function reason(string $code, int $i): string
    {
        $reasons = [
            'ANNUAL' => ['سفر عائلي', 'إجازة اعتيادية', 'ظرف شخصي', 'زيارة أقارب'],
            'EMERGENCY' => ['ظرف طارئ في الأسرة', 'حادث مروري', 'وفاة قريب'],
            'SICK' => ['مراجعة طبية', 'وعكة صحية', 'عملية جراحية بسيطة'],
            'UNPAID' => ['ظرف خاص', 'سفر طويل'],
            'MATERNITY' => ['إجازة وضع'],
            'OFFICIAL_MISSION' => ['دورة تدريبية في المديرية', 'مهمة رسمية', 'ورشة عمل'],
            'HOURLY' => ['مراجعة دائرة رسمية', 'موعد طبي', 'استلام معاملة'],
        ][$code] ?? ['إجازة'];

        return $reasons[$i % count($reasons)];
    }

    private function say(string $line): void
    {
        $this->command?->info($line);
    }
}
