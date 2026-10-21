<?php

namespace Database\Seeders;

use App\Application\Shared\Results\ApplicationResult;
use App\Application\Teachers\Commands\AddTeachingAssignmentCommand;
use App\Application\Teachers\Commands\AddTeachingAssignmentHandler;
use App\Application\Teachers\Commands\AssignTeacherSubjectCommand;
use App\Application\Teachers\Commands\AssignTeacherSubjectHandler;
use App\Application\Teachers\Commands\ChangeTeachersStatusCommand;
use App\Application\Teachers\Commands\ChangeTeachersStatusHandler;
use App\Application\Teachers\Commands\RegisterTeacherCommand;
use App\Application\Teachers\Commands\RegisterTeacherHandler;
use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\TeacherStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Teachers 51–100 of the e2e dataset (T-E051 … T-E100), on top of the 50 of {@see TeachersScenarioSeeder}, through
 * the real handlers — so the first 50 keep the timetable scenario they were built for:
 *
 * - 051–075  اعدادية التحرير, qualified (subjects) but not placed — the substitute pool for leave cover;
 * - 076–085  registered in اعدادية العباس only (school isolation), three of them teaching its curriculum;
 * - 086–092  اعدادية التحرير with an academic title and a timetable abbreviation;
 * - 093–097  inactive;
 * - 098–100  newly hired this year: no subjects yet, no grandfather name.
 *
 * Negative cases go through the same handler and must be refused (counted in {@see $summary}); an accepted one
 * stops the run. Deterministic codes, national ids and idempotency keys (batch id) — re-running adds nothing.
 */
final class E2eTeachersSeeder extends Seeder
{
    private const FIRST = 51;

    private const LAST = 100;

    private const SCHOOL = 'اعدادية التحرير';

    private const SECOND_SCHOOL = 'اعدادية العباس';

    /** spec → subjects the teacher is qualified for (catalogue names). */
    private const PROFILES = [
        ['لغة عربية', ['اللغة العربية']],
        ['لغة انكليزية', ['اللغة الانكليزية']],
        ['رياضيات', ['الرياضيات', 'الطبيعيات']],
        ['فيزياء', ['الطبيعيات']],
        ['تربية اسلامية', ['التربية الاسلامية']],
        ['هندسة كهربائية', ['العلوم الصناعية', 'التدريب العملي']],
        ['هندسة ميكانيك', ['الرسم الصناعي', 'التدريب العملي']],
        ['هندسة حاسبات', ['شبكات الحاسوب', 'صيانة الحاسوب']],
        ['هندسة الكترونيك', ['المعالجات الدقيقة']],
        ['محاسبة', ['المحاسبة الادارية']],
        ['اقتصاد', ['الاقتصاد الكلي']],
        ['إنتاج حيواني', ['تربية الدواجن']],
        ['وقاية نبات', ['وقاية المزروعات']],
        ['تصميم داخلي', ['تقنيات الديكور']],
        ['سياحة', ['الارشاد السياحي']],
    ];

    private const FIRST_NAMES = ['حسن', 'زهراء', 'مرتضى', 'آيات', 'عباس', 'سجى', 'منتظر', 'رسل', 'علي', 'بنين', 'محمد', 'دعاء', 'أحمد', 'تبارك', 'حيدر', 'نرجس', 'سجاد', 'زينب', 'عمار', 'فاطمة', 'يوسف', 'مريم', 'مصطفى', 'هدى', 'كاظم'];

    private const FATHER_NAMES = ['جاسم', 'كريم', 'عبد الحسين', 'ناصر', 'فلاح', 'رحيم', 'صباح', 'عبد الرضا', 'جبار', 'ستار'];

    private const GRANDFATHER_NAMES = ['محمد', 'علي', 'حسن', 'كاظم', 'عبد الله', 'جواد', 'مهدي'];

    private const FAMILY_NAMES = ['الموسوي', 'الكعبي', 'الزيدي', 'الساعدي', 'العامري', 'الشمري', 'التميمي', 'الربيعي', 'الخزاعي', 'الجبوري', 'العبيدي', 'آل طعمة'];

    /** @var array<string, mixed> created / refused counts */
    public array $summary = ['registered' => 0, 'qualified' => 0, 'assignments' => 0, 'inactive' => 0, 'refused' => []];

    private string $batch = 'sis-e2e';

    private int $schoolId = 0;

    private int $secondSchoolId = 0;

    private int $yearId = 0;

    /** @var array<string, int> */
    private array $subjectIds = [];

    public function forBatch(string $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function run(): void
    {
        $this->resolveReferences();
        $inactive = [];
        $created = [];
        for ($n = self::FIRST; $n <= self::LAST; $n++) {
            $school = $n >= 76 && $n <= 85 ? $this->secondSchoolId : $this->schoolId;
            $id = $this->register($n, $school);
            $created[$n] = $id;
            [, $subjects] = $this->profile($n);
            if ($n <= 97) {
                foreach ($subjects as $subject) {
                    $this->expect($this->handler(AssignTeacherSubjectHandler::class)->handle(new AssignTeacherSubjectCommand(
                        $school, $id, $this->subject($subject), $this->yearId, $this->key('subject', $n, $subject),
                    )), 'subject '.$subject.' for T-E'.$n);
                    $this->summary['qualified']++;
                }
            }
            if ($n >= 93 && $n <= 97) {
                $inactive[] = $id;
            }
        }

        $this->teachSecondSchool($created);

        if ($inactive !== []) {
            $this->expect($this->handler(ChangeTeachersStatusHandler::class)->handle(new ChangeTeachersStatusCommand(
                $this->schoolId, $inactive, TeacherStatus::Inactive, $this->key('inactive', 0),
            )), 'inactive teachers');
            $this->summary['inactive'] = count($inactive);
        }

        $this->negatives();
        $this->command?->info('E2eTeachersSeeder: '.json_encode($this->summary, JSON_UNESCAPED_UNICODE));
    }

    private function register(int $n, int $schoolId): int
    {
        $code = 'T-E'.sprintf('%03d', $n);
        $existing = DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('employee_code', $code)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }
        [$spec] = $this->profile($n);
        $newlyHired = $n >= 98;
        $titled = $n >= 86 && $n <= 92;
        $result = $this->handler(RegisterTeacherHandler::class)->handle(new RegisterTeacherCommand(
            schoolId: $schoolId,
            academicYearId: $this->yearId,
            employeeCode: $code,
            firstName: self::FIRST_NAMES[$n % count(self::FIRST_NAMES)],
            lastName: self::FAMILY_NAMES[$n % count(self::FAMILY_NAMES)],
            nationalId: $this->nationalId($n),
            specializationField: $spec,
            hireDate: $newlyHired ? '2026-09-01' : sprintf('%d-%02d-01', 1995 + ($n % 30), 1 + ($n % 12)),
            userId: null,
            idempotencyKey: $this->key('register', $n),
            fatherName: self::FATHER_NAMES[$n % count(self::FATHER_NAMES)],
            grandfatherName: $newlyHired ? null : self::GRANDFATHER_NAMES[$n % count(self::GRANDFATHER_NAMES)],
            employmentType: 1 + ($n % 5),
            academicTitleId: $titled ? 1 + ($n % 9) : null,
            abbreviation: $titled ? 'م'.($n - 85) : null,
        ));
        $this->expect($result, 'register '.$code);
        $this->summary['registered']++;

        return (int) $result->teacherId;
    }

    /** اعدادية العباس has one curriculum («تجميع وصيانة الحاسوب» — الأول): one teacher per subject. */
    private function teachSecondSchool(array $created): void
    {
        $department = DB::table(SchemaHelper::qualified('organization', 'departments').' as d')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'd.branch_id')
            ->where('b.school_id', $this->secondSchoolId)->where('d.status', 1)->where('d.name', 'تجميع وصيانة الحاسوب')
            ->first(['d.id', 'd.branch_id']);
        if ($department === null) {
            throw new RuntimeException('E2eTeachersSeeder: «تجميع وصيانة الحاسوب» is missing in '.self::SECOND_SCHOOL);
        }
        foreach ([76 => 'اللغة العربية', 80 => 'التربية الاسلامية', 83 => 'صيانة الحاسوب'] as $n => $subject) {
            $this->handler(AssignTeacherSubjectHandler::class)->handle(new AssignTeacherSubjectCommand(
                $this->secondSchoolId, $created[$n], $this->subject($subject), $this->yearId, $this->key('subject', $n, $subject),
            ));
            $this->expect($this->handler(AddTeachingAssignmentHandler::class)->handle(new AddTeachingAssignmentCommand(
                schoolId: $this->secondSchoolId,
                academicYearId: $this->yearId,
                teacherId: $created[$n],
                subjectId: $this->subject($subject),
                branchId: (int) $department->branch_id,
                departmentId: (int) $department->id,
                classId: null,
                sectionId: null,
                idempotencyKey: $this->key('teach', $n, $subject),
            )), 'teaching '.$subject.' for T-E'.$n);
            $this->summary['assignments']++;
        }
    }

    /** Each case must be refused by the handler; the refusal code is what the UI shows. */
    private function negatives(): void
    {
        $base = fn (string $code, ?string $nationalId, string $first, string $case): RegisterTeacherCommand => new RegisterTeacherCommand(
            schoolId: $this->schoolId,
            academicYearId: $this->yearId,
            employeeCode: $code,
            firstName: $first,
            lastName: 'الاختبار',
            nationalId: $nationalId,
            specializationField: 'رياضيات',
            hireDate: '2020-09-01',
            userId: null,
            idempotencyKey: $this->key('negative', 0, $case),
        );
        $cases = [
            'duplicate employee code' => $base('T-E051', $this->nationalId(901), 'مكرر', 'code'),
            'duplicate employee code (lower case)' => $base('t-e052', $this->nationalId(902), 'مكرر', 'code-lower'),
            'duplicate national id' => $base('T-E901', $this->nationalId(51), 'مكرر', 'national-id'),
            'blank first name' => $base('T-E902', $this->nationalId(903), '   ', 'blank-name'),
            'blank employee code' => $base('  ', $this->nationalId(904), 'بلا رمز', 'blank-code'),
        ];
        foreach ($cases as $label => $command) {
            $result = $this->handler(RegisterTeacherHandler::class)->handle($command);
            if (! $result->failed()) {
                throw new RuntimeException("E2eTeachersSeeder: «{$label}» was accepted — the rule is missing.");
            }
            $this->summary['refused'][$label] = implode(', ', $result->errors);
        }
    }

    /** @return array{0: string, 1: list<string>} */
    private function profile(int $n): array
    {
        return self::PROFILES[$n % count(self::PROFILES)];
    }

    private function nationalId(int $n): string
    {
        return '1991'.str_pad((string) $n, 8, '0', STR_PAD_LEFT);
    }

    private function key(string $what, int $n, string $extra = ''): string
    {
        // audit.idempotency_keys.key is VARCHAR(64).
        return $this->batch.':t:'.$what.':'.$n.($extra !== '' ? ':'.substr(md5($extra), 0, 10) : '');
    }

    private function resolveReferences(): void
    {
        $schools = DB::table(SchemaHelper::qualified('organization', 'schools'))->pluck('id', 'name');
        $this->schoolId = (int) ($schools[self::SCHOOL] ?? throw new RuntimeException('Missing school '.self::SCHOOL));
        $this->secondSchoolId = (int) ($schools[self::SECOND_SCHOOL] ?? throw new RuntimeException('Missing school '.self::SECOND_SCHOOL));
        $this->yearId = (int) (DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('is_current', true)->value('id')
            ?? throw new RuntimeException('No current academic year.'));
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->schoolId]);
        $this->subjectIds = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->pluck('id', 'name')->map(fn ($id): int => (int) $id)->all();
    }

    private function subject(string $name): int
    {
        return $this->subjectIds[$name] ?? throw new RuntimeException("Unknown subject: {$name}");
    }

    private function expect(ApplicationResult $result, string $what): void
    {
        if ($result->failed()) {
            throw new RuntimeException("E2eTeachersSeeder: {$what} failed — ".implode(', ', $result->errors));
        }
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
