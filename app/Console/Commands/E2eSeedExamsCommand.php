<?php

namespace App\Console\Commands;

use App\Application\Exams\Commands\CreateExamCommand;
use App\Application\Exams\Commands\CreateExamEnrollmentCommand;
use App\Application\Exams\Commands\CreateExamEnrollmentHandler;
use App\Application\Exams\Commands\CreateExamHandler;
use App\Application\Exams\Commands\CreateExamSessionCommand;
use App\Application\Exams\Commands\CreateExamSessionHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\OpenExamSessionCommand;
use App\Application\Exams\Commands\OpenExamSessionHandler;
use App\Application\Teachers\Commands\AddTeachingAssignmentCommand;
use App\Application\Teachers\Commands\AddTeachingAssignmentHandler;
use App\Application\Teachers\Commands\RegisterTeacherCommand;
use App\Application\Teachers\Commands\RegisterTeacherHandler;
use App\Database\DisposableE2eDatabase;
use App\Database\ProtectedDatabaseGuard;
use App\Database\SchemaHelper;
use App\Models\User;
use App\Security\Context\SchoolContext;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Exam fixtures for the grade-entry sheet e2e spec (e2e/grades/grade-sheet.spec.ts), on a DISPOSABLE copy of the
 * e2e dataset only — never on `sis` and never on the shared `sis_e2e` itself:
 *
 * 1. guard: not production · PostgreSQL · name `sis_e2e_<suffix>` · allowed by {@see ProtectedDatabaseGuard} ·
 *    the e2e dataset (schools + personas) is present;
 * 2. a synthetic grade-teacher account (`exam-grader@sis.test`, password SIS_E2E_PASSWORD, role grades_teacher in
 *    اعدادية التحرير) linked to a synthetic teacher T-X901 who teaches one subject in class الأول / section A;
 * 3. through the real command handlers: an exam with an open session (4 seats of section A — the first already graded —
 *    and 2 of section B), a scheduled (not open) session, an open session of another subject, and an open session in
 *    اعدادية العباس.
 *
 * Every run adds a new tagged set (no update, no delete), so it can be re-run before every spec run; the ids go to
 * storage/app/e2e/exam-fixture.json together with the database name.
 *
 * DB_DATABASE=sis_e2e_exams SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_e2e_exams SIS_E2E_PASSWORD=… php artisan sis:e2e-seed-exams
 */
class E2eSeedExamsCommand extends Command
{
    protected $signature = 'sis:e2e-seed-exams {--tag= : Run tag (default: base-36 time)}';

    protected $description = 'Exam / teaching-assignment fixtures for the grade-sheet e2e spec (disposable sis_e2e_* copies only)';

    public const GRADER_EMAIL = 'exam-grader@sis.test';

    private const TEACHER_CODE = 'T-X901';

    private const SCHOOL = 'اعدادية التحرير';

    private const SECOND_SCHOOL = 'اعدادية العباس';

    private string $tag = '';

    public function handle(): int
    {
        $refusal = $this->refusal();
        if ($refusal !== null) {
            $this->components->error($refusal);

            return self::FAILURE;
        }

        $this->tag = (string) ($this->option('tag') ?: strtoupper(base_convert((string) time(), 10, 36)));
        // Everything is looked up before the first write: a dataset that does not fit fails with nothing written.
        $ctx = $this->placement();
        $a = $this->enrollments($ctx, $ctx['section_a'], 6);
        $b = $this->enrollments($ctx, $ctx['section_b'], 2);

        $graderId = $this->grader($ctx['school'], (string) env('SIS_E2E_PASSWORD'));
        $this->teacher($ctx, $graderId);

        $context = app(SchoolContext::class);
        $context->set($ctx['school']);
        $examId = $this->exam($ctx['school'], $ctx, $ctx['admin'], 'اختبار إدخال الدرجات '.$this->tag);
        $open = $this->session($ctx['school'], $examId, $ctx['subject'], '09:00:00', $ctx['admin'], true);
        $closed = $this->session($ctx['school'], $examId, $ctx['subject'], '12:00:00', $ctx['admin'], false);
        $uncovered = $this->session($ctx['school'], $examId, $ctx['other_subject'], '14:00:00', $ctx['admin'], true);

        $seats = [
            'graded' => $this->seat($ctx['school'], $open, $a[0], $ctx['admin']),
            'ui_score' => $this->seat($ctx['school'], $open, $a[1], $ctx['admin']),
            'ui_absent' => $this->seat($ctx['school'], $open, $a[2], $ctx['admin']),
            'replay' => $this->seat($ctx['school'], $open, $a[3], $ctx['admin']),
            'other_section' => $this->seat($ctx['school'], $open, $b[0], $ctx['admin']),
            'other_section_2' => $this->seat($ctx['school'], $open, $b[1], $ctx['admin']),
            'closed' => $this->seat($ctx['school'], $closed, $a[4], $ctx['admin']),
            'uncovered' => $this->seat($ctx['school'], $uncovered, $a[5], $ctx['admin']),
        ];
        app(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $ctx['school'], examEnrollmentId: $seats['graded'], score: '70', isAbsent: false,
            enteredBy: $ctx['admin'], idempotencyKey: "e2e-exams:{$this->tag}:grade",
        ));

        $context->set($ctx['second_school']);
        $foreignExam = $this->exam($ctx['second_school'], $ctx, $ctx['school2_admin'], 'اختبار مدرسة أخرى '.$this->tag);
        $foreign = $this->session($ctx['second_school'], $foreignExam, $ctx['subject'], '09:00:00', $ctx['school2_admin'], true);
        $context->clear();

        $manifest = [
            'database' => DB::connection()->getDatabaseName(),
            'tag' => $this->tag,
            'grader_email' => self::GRADER_EMAIL,
            'academic_year_id' => $ctx['year'],
            'sessions' => ['open' => $open, 'closed' => $closed, 'uncovered' => $uncovered, 'foreign' => $foreign],
            'seats' => $seats,
        ];
        $path = storage_path('app/e2e/exam-fixture.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->components->info("Exam fixture {$this->tag} written to {$path}");
        $this->line((string) json_encode($manifest, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    /** `sis_e2e_<suffix>` only: the live `sis`, the shared `sis_e2e` and every other name are refused. */
    public static function isDisposableCopy(string $database): bool
    {
        return DisposableE2eDatabase::isDisposableName($database);
    }

    /** Null when the connection is a disposable copy of the e2e dataset; otherwise why it is refused. */
    private function refusal(): ?string
    {
        if (app()->environment('production')) {
            return 'Refused: production environment.';
        }
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return 'Refused: the e2e fixtures need PostgreSQL.';
        }
        $database = (string) DB::connection()->getDatabaseName();
        if (! self::isDisposableCopy($database)) {
            return "Refused: «{$database}» is not a disposable e2e copy (name must be sis_e2e_<suffix>; sis and sis_e2e are never used).";
        }
        try {
            app(ProtectedDatabaseGuard::class)->assertSafeForDestructiveOperations();
        } catch (\Throwable $e) {
            return 'Refused: '.$e->getMessage();
        }
        if (mb_strlen((string) env('SIS_E2E_PASSWORD', '')) < 12) {
            return 'Refused: SIS_E2E_PASSWORD (12+ characters, the one of sis:e2e-seed) is required.';
        }
        $personas = DB::table('users')->whereIn('email', ['e2e-admin@sis.test', 'e2e-school2@sis.test'])->count();
        $schools = DB::table(SchemaHelper::qualified('organization', 'schools'))->whereIn('name', [self::SCHOOL, self::SECOND_SCHOOL])->count();

        return $personas === 2 && $schools === 2 ? null : 'Refused: the e2e dataset (sis:e2e-seed) is not in this database.';
    }

    /**
     * Placement of the fixture: class الأول of the current year, sections A and B, the branch most of section A's
     * students are placed in, and two subjects of that branch's curricula for the class's grade. The assignment is
     * department-less (the whole branch inside class الأول / section A), so it does not depend on the departments.
     *
     * @return array<string, int>
     */
    private function placement(): array
    {
        $school = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->where('name', self::SCHOOL)->value('id');
        $second = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->where('name', self::SECOND_SCHOOL)->value('id');
        $year = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('is_current', true)->orderBy('id')->value('id');
        $term = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->where('academic_year_id', $year)->orderBy('term_order')->value('id');
        $class = DB::table(SchemaHelper::qualified('enrollment', 'classes'))->where('school_id', $school)->where('academic_year_id', $year)->where('code', 'CLS-1')->first(['id', 'grade_level_id']);
        $section = fn (string $code): int => (int) DB::table(SchemaHelper::qualified('enrollment', 'sections'))->where('class_id', $class?->id)->where('code', $code)->value('id');
        $sectionA = $section('SEC-A');
        $branch = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('section_id', $sectionA)->where('status', 1)->whereNotNull('branch_id')
            ->groupBy('branch_id')->orderByRaw('count(*) DESC, branch_id')->value('branch_id');
        $subjects = DB::table(SchemaHelper::qualified('curriculum', 'curricula').' as cu')
            ->join(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs', 'cs.curriculum_id', '=', 'cu.id')
            ->join(SchemaHelper::qualified('organization', 'departments').' as d', 'd.id', '=', 'cu.department_id')
            ->where('cu.school_id', $school)->where('cu.academic_year_id', $year)->where('cu.grade_level_id', $class?->grade_level_id)
            ->where('d.branch_id', $branch)->where('cu.status', 1)->where('cs.status', 1)
            ->distinct()->orderBy('cs.subject_id')->limit(2)->pluck('cs.subject_id')->map(fn ($id): int => (int) $id)->all();
        $user = fn (string $email): int => (int) DB::table('users')->where('email', $email)->value('id');

        $ctx = [
            'school' => $school, 'second_school' => $second, 'year' => $year, 'term' => $term,
            'class' => (int) $class?->id, 'section_a' => $sectionA, 'section_b' => $section('SEC-B'),
            'branch' => $branch,
            'subject' => $subjects[0] ?? 0, 'other_subject' => $subjects[1] ?? 0,
            'admin' => $user('e2e-admin@sis.test'), 'school2_admin' => $user('e2e-school2@sis.test'),
        ];
        $missing = array_keys(array_filter($ctx, static fn (int $v): bool => $v === 0));
        if ($missing !== []) {
            throw new RuntimeException('The e2e dataset lacks: '.implode(', ', $missing));
        }

        return $ctx;
    }

    private function grader(int $school, string $password): int
    {
        $user = User::query()->where('email', self::GRADER_EMAIL)->first() ?? new User;
        $user->forceFill(['name' => 'مدخل درجات الاختبار', 'email' => self::GRADER_EMAIL, 'password' => Hash::make($password), 'email_verified_at' => now()])->save();
        DB::table(SchemaHelper::qualified('security', 'user_roles'))->where('user_id', $user->id)->delete();
        $roles = app(SecurityPermissionSeeder::class);
        $roles->assignRole($user, 'grades_teacher', $school);

        return (int) $user->id;
    }

    /** @param  array<string, int>  $ctx */
    private function teacher(array $ctx, int $graderId): void
    {
        $teacherId = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('employee_code', self::TEACHER_CODE)->value('id');
        if ($teacherId === 0) {
            $registered = app(RegisterTeacherHandler::class)->handle(new RegisterTeacherCommand(
                schoolId: $ctx['school'], academicYearId: $ctx['year'], employeeCode: self::TEACHER_CODE,
                firstName: 'معلم', lastName: 'درجات اختبار', nationalId: null, specializationField: null, hireDate: null,
                userId: $graderId, idempotencyKey: 'e2e-exams:teacher',
            ));
            $teacherId = (int) ($registered->teacherId ?? throw new RuntimeException('Registering '.self::TEACHER_CODE.' failed: '.implode(', ', $registered->errors)));
        }

        $assigned = app(AddTeachingAssignmentHandler::class)->handle(new AddTeachingAssignmentCommand(
            schoolId: $ctx['school'], academicYearId: $ctx['year'], teacherId: $teacherId, subjectId: $ctx['subject'],
            branchId: $ctx['branch'], departmentId: null, classId: $ctx['class'], sectionId: $ctx['section_a'],
            idempotencyKey: "e2e-exams:{$this->tag}:assignment",
        ));
        if ($assigned->failed() && $assigned->errors !== ['teachers.assignment_exists']) {
            throw new RuntimeException('Teaching assignment failed: '.implode(', ', $assigned->errors));
        }
    }

    /** @param  array<string, int>  $ctx */
    private function exam(int $school, array $ctx, int $actor, string $name): int
    {
        $typeId = (int) (DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('code', 'E2E-GRADES')->value('id')
            ?? DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId(['code' => 'E2E-GRADES', 'name' => 'اختبار الدرجات', 'weight_percentage' => 100]));

        return (int) app(CreateExamHandler::class)->handle(new CreateExamCommand(
            schoolId: $school, academicYearId: $ctx['year'], termId: $ctx['term'], examTypeId: $typeId, name: $name,
            startDate: now()->toDateString(), endDate: now()->addDays(7)->toDateString(), actorUserId: $actor,
            idempotencyKey: "e2e-exams:{$this->tag}:exam:{$school}",
        ))->examId;
    }

    private function session(int $school, int $examId, int $subject, string $start, int $actor, bool $open): int
    {
        $id = (int) app(CreateExamSessionHandler::class)->handle(new CreateExamSessionCommand(
            schoolId: $school, examId: $examId, subjectId: $subject, sessionDate: now()->toDateString(), startTime: $start,
            endTime: substr_replace($start, (string) ((int) substr($start, 0, 2) + 1), 0, 2), actorUserId: $actor,
            idempotencyKey: "e2e-exams:{$this->tag}:session:{$examId}:{$start}",
        ))->examSessionId;
        if ($open) {
            app(OpenExamSessionHandler::class)->handle(new OpenExamSessionCommand(
                schoolId: $school, examSessionId: $id, actorUserId: $actor, idempotencyKey: "e2e-exams:{$this->tag}:open:{$id}",
            ));
        }

        return $id;
    }

    /**
     * @param  array<string, int>  $ctx
     * @return list<int>
     */
    private function enrollments(array $ctx, int $section, int $count): array
    {
        $ids = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $ctx['school'])->where('academic_year_id', $ctx['year'])->where('section_id', $section)
            ->where('status', 1)->where('branch_id', $ctx['branch'])
            ->orderBy('id')->limit($count)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if (count($ids) < $count) {
            throw new RuntimeException("Section {$section} has fewer than {$count} placed enrollments.");
        }

        return $ids;
    }

    private function seat(int $school, int $session, int $enrollment, int $actor): int
    {
        return (int) app(CreateExamEnrollmentHandler::class)->handle(new CreateExamEnrollmentCommand(
            schoolId: $school, examSessionId: $session, enrollmentId: $enrollment, actorUserId: $actor,
            idempotencyKey: "e2e-exams:{$this->tag}:seat:{$session}:{$enrollment}",
        ))->examEnrollmentId;
    }
}
