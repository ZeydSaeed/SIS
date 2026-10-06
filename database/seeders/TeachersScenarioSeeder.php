<?php

namespace Database\Seeders;

use App\Application\Curriculum\Commands\CreateCurriculumCommand;
use App\Application\Curriculum\Commands\CreateCurriculumHandler;
use App\Application\Enrollment\Commands\UpdateSectionCommand;
use App\Application\Enrollment\Commands\UpdateSectionHandler;
use App\Application\Shared\Results\ApplicationResult;
use App\Application\Teachers\Commands\AddTeachingAssignmentCommand;
use App\Application\Teachers\Commands\AddTeachingAssignmentHandler;
use App\Application\Teachers\Commands\AssignTeacherSchoolCommand;
use App\Application\Teachers\Commands\AssignTeacherSchoolHandler;
use App\Application\Teachers\Commands\AssignTeacherSubjectCommand;
use App\Application\Teachers\Commands\AssignTeacherSubjectHandler;
use App\Application\Teachers\Commands\ChangeTeachersStatusCommand;
use App\Application\Teachers\Commands\ChangeTeachersStatusHandler;
use App\Application\Teachers\Commands\RegisterTeacherCommand;
use App\Application\Teachers\Commands\RegisterTeacherHandler;
use App\Application\Teachers\Commands\SetTeachersEmploymentTypeCommand;
use App\Application\Teachers\Commands\SetTeachersEmploymentTypeHandler;
use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\TeacherStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 50 teachers covering every case of the «المعلمون» page, written through the real handlers:
 *
 * - names with / without father or grandfather, compound names (عبد ...), long family names;
 * - certificate specialization (تخصص الشهادة) set or missing;
 * - every نوع التعيين (ملاك · مكلف · تنسيب · محاضر · عقد) and «غير محدد»; active and inactive;
 * - teaching per department, branch-wide (كل اختصاصات الفرع), per class and per section,
 *   several subjects / departments / branches per teacher, subjects without a place, no subjects;
 * - teachers of two schools (اعدادية التحرير + اعدادية العباس) with a curriculum in the second;
 * - homeroom teachers (رائد الصف) on some sections.
 *
 * Idempotent: deterministic idempotency keys and employee codes (T-S001 … T-S050) — re-running
 * returns the cached results. Usage: php artisan db:seed --class=TeachersScenarioSeeder
 */
final class TeachersScenarioSeeder extends Seeder
{
    private const SCHOOL = 'اعدادية التحرير';

    private const SECOND_SCHOOL = 'اعدادية العباس';

    /** Branch / department / class / section names → ids (resolved from the database). */
    private array $branchIds = [];

    private array $departmentIds = [];

    private array $classIds = [];

    private array $sectionIds = [];

    /** department id → its branch id (branch names are not unique). */
    private array $departmentBranch = [];

    private array $subjectIds = [];

    private int $schoolId = 0;

    private int $secondSchoolId = 0;

    private int $yearId = 0;

    public function run(): void
    {
        $this->resolveReferences();
        $this->ensureSecondSchoolCurriculum();

        $teacherIds = [];
        foreach ($this->teachers() as $index => $def) {
            $number = sprintf('%03d', $index + 1);
            $teacherIds[$number] = $this->register($number, $def);

            if (($def['type'] ?? null) !== null) {
                $this->expect($this->handler(SetTeachersEmploymentTypeHandler::class)->handle(new SetTeachersEmploymentTypeCommand(
                    $this->schoolId, $this->yearId, [$teacherIds[$number]], $def['type'], 'seed-teachers:type:'.$number,
                )), 'employment type '.$number);
            }

            foreach ($def['teach'] ?? [] as $i => $teach) {
                $this->teach($teacherIds[$number], $teach, $number.':'.$i);
            }

            foreach ($def['subjects_only'] ?? [] as $subject) {
                $this->expect($this->handler(AssignTeacherSubjectHandler::class)->handle(new AssignTeacherSubjectCommand(
                    $this->schoolId, $teacherIds[$number], $this->subject($subject), $this->yearId, 'seed-teachers:subject:'.$number.':'.$subject,
                )), 'subject '.$subject.' for '.$number);
            }

            if (isset($def['second_school'])) {
                $this->joinSecondSchool($teacherIds[$number], $number, $def['second_school']);
            }
        }

        $inactive = [];
        foreach ($this->teachers() as $index => $def) {
            if (($def['inactive'] ?? false) === true) {
                $inactive[] = $teacherIds[sprintf('%03d', $index + 1)];
            }
        }
        if ($inactive !== []) {
            $this->expect($this->handler(ChangeTeachersStatusHandler::class)->handle(new ChangeTeachersStatusCommand(
                $this->schoolId, $inactive, TeacherStatus::Inactive, 'seed-teachers:inactive',
            )), 'inactive teachers');
        }

        $this->assignHomerooms($teacherIds);

        $this->command?->info('TeachersScenarioSeeder: '.count($teacherIds).' teachers ready in '.self::SCHOOL.'.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function teachers(): array
    {
        // teach: [subject, branch, department|null, class|null, section|null]
        return [
            ['name' => ['زيد', 'سعيد', 'محمد', 'الجبوري'], 'spec' => 'هندسة حاسبات', 'type' => 1, 'teach' => [['شبكات الحاسوب', 'الحاسوب وتقنية المعلومات', 'شبكات الحاسوب', null, null], ['تطبيقيات الاتصالات', 'الحاسوب وتقنية المعلومات', 'شبكات الحاسوب', 'الثاني', 'ب']]],
            ['name' => ['حسين', 'علي', 'كاظم', 'الموسوي'], 'spec' => 'علوم حاسوب', 'type' => 1, 'teach' => [['صيانة الحاسوب', 'الحاسوب وتقنية المعلومات', 'تجميع وصيانة الحاسوب', null, null], ['مختبر وشبكات الانترنت', 'الحاسوب وتقنية المعلومات', 'تجميع وصيانة الحاسوب', 'الأول', 'شعبة أ']]],
            ['name' => ['مريم', 'عبد الله', 'جاسم', 'التميمي'], 'spec' => 'هندسة اتصالات', 'type' => 2, 'teach' => [['تكنلوجيا المحمول والاجهزة اللوحية', 'الحاسوب وتقنية المعلومات', 'اجهزة الهاتف والحاسوب المحمولة', null, null], ['صيانة الاجهزة الخلوية', 'الحاسوب وتقنية المعلومات', 'اجهزة الهاتف والحاسوب المحمولة', null, null]]],
            ['name' => ['عبد الرحمن', 'خالد', 'عبد الكريم', 'العبيدي'], 'spec' => 'هندسة الكترونيك', 'type' => 3, 'teach' => [['المعالجات الدقيقة', 'الحاسوب وتقنية المعلومات', null, null, null]], 'second_school' => ['subject' => 'صيانة الحاسوب', 'department' => 'تجميع وصيانة الحاسوب']],
            ['name' => ['فاطمة', 'حسن', 'عباس', 'الحسيني'], 'spec' => 'لغة عربية', 'type' => 1, 'teach' => [['اللغة العربية', 'الصناعي', null, 'الأول', null], ['اللغة العربية', 'الحاسوب وتقنية المعلومات', null, 'الأول', null]]],
            ['name' => ['أحمد', 'محمود', null, 'الدليمي'], 'spec' => 'لغة انكليزية', 'type' => 1, 'teach' => [['اللغة الانكليزية', 'الحاسوب وتقنية المعلومات', null, null, null], ['اللغة الانكليزية والمراسلات التجارية', 'التجاري', 'الادارة', null, null]]],
            ['name' => ['سارة', 'يوسف', 'إبراهيم', 'الشمري'], 'spec' => 'رياضيات', 'type' => 2, 'teach' => [['الرياضيات', 'الصناعي', 'كهرباء', 'الأول', 'شعبة أ'], ['الرياضيات', 'الصناعي', 'ميكانيك', 'الأول', 'ب'], ['الرياضيات', 'الحاسوب وتقنية المعلومات', 'شبكات الحاسوب', null, null]]],
            ['name' => ['علي', 'حسين', 'مهدي', 'الربيعي'], 'spec' => 'فيزياء', 'type' => 4, 'teach' => [['الطبيعيات', 'الصناعي', null, null, null]]],
            ['name' => ['محمد', 'باقر', 'جعفر', 'الحكيم'], 'spec' => 'تربية اسلامية', 'type' => 1, 'teach' => [['التربية الاسلامية', 'الصناعي', null, null, null], ['التربية الاسلامية', 'التجاري', null, null, null], ['التربية الاسلامية', 'الزراعي', null, null, null]]],
            ['name' => ['نور', 'الهدى', 'سالم', 'العزاوي'], 'spec' => 'هندسة كهربائية', 'type' => 1, 'teach' => [['العلوم الصناعية', 'الصناعي', 'كهرباء', null, null], ['التدريب العملي', 'الصناعي', 'كهرباء', 'الثاني', 'أ']]],
            ['name' => ['ياسر', 'عبد الأمير', 'حمزة', 'الخفاجي'], 'spec' => 'هندسة ميكانيك', 'type' => 1, 'teach' => [['الرسم الصناعي', 'الصناعي', 'ميكانيك', null, null], ['التدريب العملي', 'الصناعي', 'ميكانيك', null, null]]],
            ['name' => ['رنا', 'فاضل', 'عزيز', 'الكبيسي'], 'spec' => 'هندسة سيارات', 'type' => 5, 'teach' => [['التدريب العملي', 'الصناعي', 'السيارات', null, null]]],
            ['name' => ['عمر', 'فاروق', 'نجم', 'السامرائي'], 'spec' => 'هندسة تبريد وتكييف', 'type' => 2, 'teach' => [['العلوم الصناعية', 'الصناعي', 'تكييف الهواء و التثليج', null, null]]],
            ['name' => ['هدى', 'كريم', 'شاكر', 'البياتي'], 'spec' => 'هندسة طبية', 'type' => 3, 'teach' => [['العلوم الصناعية', 'الصناعي', 'الأجهزة الطبية', null, null], ['التدريب العملي', 'الصناعي', 'الأجهزة الطبية', null, null]]],
            ['name' => ['مصطفى', 'جبار', 'عودة', 'الساعدي'], 'spec' => 'هندسة الكترونيك وسيطرة', 'type' => 1, 'teach' => [['التدريب العملي', 'الصناعي', 'الكترونيك وسيطرة', null, null]]],
            ['name' => ['زينب', 'عادل', 'طه', 'النعيمي'], 'spec' => 'هندسة مدنية', 'type' => 4, 'teach' => [['الرسم الصناعي', 'الصناعي', 'البناء', null, null]]],
            ['name' => ['كرار', 'حيدر', 'صالح', 'المالكي'], 'spec' => 'هندسة كيمياوية', 'type' => 5, 'teach' => [['العلوم الصناعية', 'الصناعي', 'الصناعات البتروكيمياوية', null, null]]],
            ['name' => ['إيمان', 'رشيد', 'حميد', 'الجنابي'], 'spec' => 'محاسبة', 'type' => 1, 'teach' => [['المحاسبة الادارية', 'التجاري', 'الادارة', null, null], ['محاسبة الشركات', 'التجاري', 'المحاسبة', null, null], ['محاسبة التكاليف', 'التجاري', 'المحاسبة', null, null]]],
            ['name' => ['سيف', 'الدين', 'قاسم', 'العاني'], 'spec' => 'إدارة أعمال', 'type' => 2, 'teach' => [['ادارة الموارد البشرية', 'التجاري', 'الادارة', null, null], ['ادارة الانتاج والعمليات', 'التجاري', 'الادارة', null, null]]],
            ['name' => ['لمى', 'هاشم', 'ناجي', 'الحديثي'], 'spec' => 'اقتصاد', 'type' => 3, 'teach' => [['الاقتصاد الكلي', 'التجاري', null, null, null]]],
            ['name' => ['باسم', 'نعمة', 'غازي', 'الزبيدي'], 'spec' => 'علوم مالية ومصرفية', 'type' => 4, 'teach' => [['الادارة المالية', 'التجاري', 'الادارة', null, null], ['محاسبة المنشات المالية', 'التجاري', 'المحاسبة', null, null]]],
            ['name' => ['أسماء', 'وليد', 'خليل', 'القيسي'], 'spec' => 'إنتاج حيواني', 'type' => 1, 'teach' => [['تربية الدواجن', 'الزراعي', 'زراعي', null, null], ['صناعة الالبان', 'الزراعي', 'زراعي', null, null]]],
            ['name' => ['حيدر', 'عبد الزهرة', 'لفتة', 'الكعبي'], 'spec' => 'وقاية نبات', 'type' => 2, 'teach' => [['وقاية المزروعات', 'الزراعي', 'زراعي', null, null], ['انتاج الفاكهة', 'الزراعي', 'زراعي', null, null]]],
            ['name' => ['شيماء', 'ستار', 'جبر', 'العطواني'], 'spec' => 'اقتصاد منزلي', 'type' => 1, 'teach' => [['فن التفصيل والخياطة', 'الفنون التطبيقية', 'فن التربية الاسرية', null, null], ['التغذية', 'الفنون التطبيقية', 'فن التربية الاسرية', null, null]]],
            ['name' => ['رغد', 'منذر', 'فيصل', 'الطائي'], 'spec' => 'تربية أسرية', 'type' => 5, 'teach' => [['تربية الطفل والعلاقات الاسرية', 'الفنون التطبيقية', 'فن التربية الاسرية', null, null], ['فن الاناقة و الازياء', 'الفنون التطبيقية', 'فن التربية الاسرية', null, null]]],
            ['name' => ['مهند', 'رعد', 'شهاب', 'الأعظمي'], 'spec' => 'تصميم داخلي', 'type' => 1, 'teach' => [['تقنيات الديكور', 'الفنون التطبيقية', 'فن الديكور', null, null], ['اخراج واضهار', 'الفنون التطبيقية', 'فن الديكور', null, null]]],
            ['name' => ['أنور', 'سعدي', 'مجيد', 'الحمداني'], 'spec' => 'نجارة وأثاث', 'type' => 4, 'teach' => [['صناعة الاثاث', 'الفنون التطبيقية', 'فن الديكور', null, null], ['مكملات الديكور', 'الفنون التطبيقية', 'فن الديكور', null, null]]],
            ['name' => ['دعاء', 'ماجد', 'رحيم', 'الشيخلي'], 'spec' => 'إدارة فنادق', 'type' => 1, 'teach' => [['المحاسبة الفندقية', 'السياحة والفندقة', null, null, null], ['المهارات العملية التدبير الفندقي', 'السياحة والفندقة', 'الاسكان الفندقي', null, null]]],
            ['name' => ['جمال', 'عبد الحسين', 'كامل', 'الفتلاوي'], 'spec' => 'سياحة', 'type' => 2, 'teach' => [['الارشاد السياحي', 'السياحة والفندقة', 'الادارة السياحية', null, null], ['التخطيط السياحي', 'السياحة والفندقة', 'الادارة السياحية', null, null], ['السياحة المستدامة', 'السياحة والفندقة', 'الادارة السياحية', null, null]]],
            ['name' => ['هبة', 'نزار', 'صبري', 'الراوي'], 'spec' => 'فن الطبخ', 'type' => 5, 'teach' => [['فن الضيافة وانتاج الاطعمة', 'السياحة والفندقة', 'الضيافة وانتاج الاظعمة', null, null], ['المهارات العملية انتاج الاطعمة', 'السياحة والفندقة', 'الضيافة وانتاج الاظعمة', null, null]]],
            ['name' => ['ضياء', 'عبد الواحد', 'هادي', 'الركابي'], 'spec' => 'إدارة سفر', 'type' => 3, 'teach' => [['ادارة السفر والحجز الالكتروني', 'السياحة والفندقة', 'الاسكان الفندقي', null, null], ['المهارات العملية المكتب الامامي', 'السياحة والفندقة', 'الاسكان الفندقي', null, null]]],
            // Several branches and departments at once.
            ['name' => ['عبد الله', 'مصطفى', 'عبد الجبار', 'آل ياسين'], 'spec' => 'لغة عربية', 'type' => 1, 'teach' => [['اللغة العربية', 'التجاري', 'الادارة', null, null], ['اللغة العربية', 'التجاري', 'المحاسبة', null, null], ['اللغة العربية', 'الزراعي', 'زراعي', null, null], ['اللغة العربية', 'السياحة والفندقة', null, null, null]]],
            ['name' => ['رقية', 'سجاد', 'مرتضى', 'الأسدي'], 'spec' => 'رياضيات', 'type' => 2, 'teach' => [['الرياضيات', 'الحاسوب وتقنية المعلومات', 'تجميع وصيانة الحاسوب', 'الأول', 'شعبة ج'], ['الرياضيات', 'الحاسوب وتقنية المعلومات', 'اجهزة الهاتف والحاسوب المحمولة', 'الثالث', null], ['الرياضيات', 'الزراعي', 'زراعي', null, null]]],
            ['name' => ['تحسين', 'مزهر', 'جودة', 'الحسناوي'], 'spec' => 'فيزياء', 'type' => 1, 'teach' => [['الطبيعيات', 'الحاسوب وتقنية المعلومات', null, 'الثالث', 'شعبة أ'], ['الطبيعيات', 'الحاسوب وتقنية المعلومات', null, 'الثالث', 'ب']]],
            ['name' => ['غادة', 'صباح', 'نوري', 'المشهداني'], 'spec' => 'لغة انكليزية', 'type' => 4, 'teach' => [['اللغة الانكليزية', 'الصناعي', null, 'الثالث', 'شعبة ج'], ['اللغة الانكليزية', 'السياحة والفندقة', 'الادارة السياحية', null, null]]],
            ['name' => ['يونس', 'إسماعيل', 'خضر', 'الجميلي'], 'spec' => 'تربية اسلامية', 'type' => 5, 'teach' => [['التربية الاسلامية', 'الحاسوب وتقنية المعلومات', null, null, null], ['التربية الاسلامية', 'السياحة والفندقة', null, null, null], ['التربية الاسلامية', 'الفنون التطبيقية', null, null, null]]],
            // Two schools.
            ['name' => ['صفاء', 'جواد', 'كاظم', 'الكربلائي'], 'spec' => 'هندسة حاسبات', 'type' => 3, 'teach' => [['صيانة الحاسوب', 'الحاسوب وتقنية المعلومات', 'تجميع وصيانة الحاسوب', 'الثاني', null]], 'second_school' => ['subject' => 'صيانة الحاسوب', 'department' => 'تجميع وصيانة الحاسوب']],
            ['name' => ['أمير', 'عبد العظيم', null, 'الحلي'], 'spec' => 'علوم حاسوب', 'type' => 4, 'teach' => [['المعالجات الدقيقة', 'الحاسوب وتقنية المعلومات', 'شبكات الحاسوب', null, null]], 'second_school' => ['subject' => 'اللغة العربية', 'department' => null]],
            // Subjects assigned without a place yet / no subjects at all.
            ['name' => ['نادية', 'فؤاد', 'حسام', 'الكرخي'], 'spec' => 'لغة انكليزية', 'type' => 2, 'subjects_only' => ['اللغة الانكليزية']],
            ['name' => ['قصي', 'هيثم', 'عصام', 'البدري'], 'spec' => 'رياضيات', 'type' => null, 'subjects_only' => ['الرياضيات', 'الطبيعيات']],
            ['name' => ['بشرى', 'نبيل', 'جميل', 'الصافي'], 'spec' => 'محاسبة', 'type' => 5],
            ['name' => ['ليث', 'عماد', null, 'الأنصاري'], 'spec' => null, 'type' => null],
            ['name' => ['ميس', 'بهاء', 'ضياء', 'الحيدري'], 'spec' => 'هندسة شبكات', 'type' => 4],
            // Inactive teachers (with and without teaching).
            ['name' => ['خليل', 'إبراهيم', 'خليل', 'الخالدي'], 'spec' => 'هندسة كهربائية', 'type' => 1, 'inactive' => true, 'teach' => [['التدريب العملي', 'الصناعي', 'كهرباء', null, null]]],
            ['name' => ['وداد', 'سلمان', 'داود', 'الشبكي'], 'spec' => 'لغة عربية', 'type' => 5, 'inactive' => true],
            ['name' => ['منتظر', 'كاظم', 'جاسم', 'الأسدي'], 'spec' => 'هندسة ميكانيك', 'type' => 4, 'inactive' => true, 'teach' => [['الرسم الصناعي', 'الصناعي', 'النجارة', null, null]]],
            ['name' => ['آلاء', 'عبد الستار', 'محمد علي', 'الطالقاني'], 'spec' => 'علوم حاسوب', 'type' => 2, 'inactive' => true],
            // Long names / single-word family names / missing grandfather.
            ['name' => ['عبد المحسن', 'عبد الرزاق', 'عبد الوهاب', 'آل عبد الجبار البغدادي'], 'spec' => 'إدارة أعمال', 'type' => 1, 'teach' => [['الاقتصاد الكلي', 'التجاري', 'المحاسبة', null, null]]],
            ['name' => ['تبارك', 'أحمد', null, 'علي'], 'spec' => 'فنون تطبيقية', 'type' => 3, 'teach' => [['تقنيات الديكور', 'الفنون التطبيقية', 'فن الديكور', null, null]]],
            ['name' => ['جعفر', 'صادق', 'باقر', 'الحسني'], 'spec' => 'هندسة اتصالات', 'type' => 1, 'teach' => [['تطبيقيات الاتصالات', 'الحاسوب وتقنية المعلومات', 'شبكات الحاسوب', 'الثاني', 'شعبة أ'], ['شبكات الحاسوب', 'الحاسوب وتقنية المعلومات', 'شبكات الحاسوب', 'الثالث', 'شعبة ج']]],
        ];
    }

    private function register(string $number, array $def): int
    {
        [$first, $father, $grandfather, $last] = $def['name'];
        $code = 'T-S'.$number;
        $existing = DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('employee_code', $code)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        $result = $this->handler(RegisterTeacherHandler::class)->handle(new RegisterTeacherCommand(
            schoolId: $this->schoolId,
            academicYearId: $this->yearId,
            employeeCode: $code,
            firstName: $first,
            lastName: $last,
            nationalId: '1990'.str_pad($number, 8, '0', STR_PAD_LEFT),
            specializationField: $def['spec'],
            hireDate: sprintf('%d-09-01', 2005 + ((int) $number % 18)),
            userId: null,
            idempotencyKey: 'seed-teachers:register:'.$number,
            fatherName: $father,
            grandfatherName: $grandfather,
        ));
        $this->expect($result, 'register '.$code);

        return (int) $result->teacherId;
    }

    /** @param  array{0:string,1:string,2:?string,3:?string,4:?string}  $teach */
    private function teach(int $teacherId, array $teach, string $key, ?int $schoolId = null): void
    {
        [$subject, $branch, $department, $class, $section] = $teach;
        $schoolId ??= $this->schoolId;

        $departmentId = $department === null ? null : $this->lookup($this->departmentIds, $schoolId.'|'.$branch.'|'.$department, 'department');
        $this->expect($this->handler(AddTeachingAssignmentHandler::class)->handle(new AddTeachingAssignmentCommand(
            schoolId: $schoolId,
            academicYearId: $this->yearId,
            teacherId: $teacherId,
            subjectId: $this->subject($subject),
            branchId: $departmentId !== null ? $this->departmentBranch[$departmentId] : $this->lookup($this->branchIds, $schoolId.'|'.$branch, 'branch'),
            departmentId: $departmentId,
            classId: $class === null ? null : $this->lookup($this->classIds, $class, 'class'),
            sectionId: $section === null ? null : $this->lookup($this->sectionIds, $class.'|'.$section, 'section'),
            idempotencyKey: 'seed-teachers:teach:'.$key,
        )), 'teaching '.$subject.' / '.$branch.' / '.($department ?? '*').' for '.$key);
    }

    /** @param  array{subject:string, department:?string}  $plan */
    private function joinSecondSchool(int $teacherId, string $number, array $plan): void
    {
        $this->expect($this->handler(AssignTeacherSchoolHandler::class)->handle(new AssignTeacherSchoolCommand(
            targetSchoolId: $this->secondSchoolId,
            sourceSchoolId: $this->schoolId,
            teacherId: $teacherId,
            academicYearId: $this->yearId,
            idempotencyKey: 'seed-teachers:second-school:'.$number,
        )), 'second school for '.$number);

        $this->expect($this->handler(SetTeachersEmploymentTypeHandler::class)->handle(new SetTeachersEmploymentTypeCommand(
            $this->secondSchoolId, $this->yearId, [$teacherId], 3, 'seed-teachers:second-type:'.$number,
        )), 'second school employment type '.$number);

        $this->teach($teacherId, [$plan['subject'], 'الحاسوب وتقنية المعلومات', $plan['department'], null, null], 'second:'.$number, $this->secondSchoolId);
    }

    /** اعدادية العباس has branches but no curriculum yet: one for «تجميع وصيانة الحاسوب». */
    private function ensureSecondSchoolCurriculum(): void
    {
        $department = $this->lookup($this->departmentIds, $this->secondSchoolId.'|الحاسوب وتقنية المعلومات|تجميع وصيانة الحاسوب', 'department');
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->secondSchoolId]);
        $exists = DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $this->secondSchoolId)
            ->where('academic_year_id', $this->yearId)
            ->where('department_id', $department)
            ->exists();
        if ($exists) {
            return;
        }

        $grade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->orderBy('level_order')->value('id');
        $result = $this->handler(CreateCurriculumHandler::class)->handle(new CreateCurriculumCommand(
            schoolId: $this->secondSchoolId,
            academicYearId: $this->yearId,
            gradeLevelId: $grade,
            name: 'تجميع وصيانة الحاسوب — الأول',
            specializationId: null,
            idempotencyKey: 'seed-teachers:second-curriculum',
            subjectIds: [$this->subject('التربية الاسلامية'), $this->subject('اللغة العربية'), $this->subject('صيانة الحاسوب')],
            departmentId: $department,
        ));
        if ($result instanceof ApplicationResult) {
            $this->expect($result, 'second school curriculum');
        }
    }

    /** @param  array<string, int>  $teacherIds */
    private function assignHomerooms(array $teacherIds): void
    {
        $plan = ['الأول|شعبة أ' => '002', 'الأول|ب' => '005', 'الثاني|أ' => '010', 'الثاني|ب' => '001', 'الثالث|شعبة أ' => '034', 'الثالث|شعبة ج' => '049'];
        foreach ($plan as $sectionKey => $number) {
            $sectionId = $this->lookup($this->sectionIds, $sectionKey, 'section');
            DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->schoolId]);
            $section = DB::table(SchemaHelper::qualified('enrollment', 'sections'))->where('id', $sectionId)->first(['name', 'capacity']);
            $this->expect($this->handler(UpdateSectionHandler::class)->handle(new UpdateSectionCommand(
                schoolId: $this->schoolId,
                sectionId: $sectionId,
                name: (string) $section->name,
                capacity: $section->capacity !== null ? (int) $section->capacity : null,
                homeroomTeacherId: $teacherIds[$number],
                idempotencyKey: 'seed-teachers:homeroom:'.$sectionKey,
            )), 'homeroom '.$sectionKey);
        }
    }

    private function resolveReferences(): void
    {
        $schools = DB::table(SchemaHelper::qualified('organization', 'schools'))->pluck('id', 'name');
        $this->schoolId = (int) ($schools[self::SCHOOL] ?? throw new RuntimeException('Missing school '.self::SCHOOL));
        $this->secondSchoolId = (int) ($schools[self::SECOND_SCHOOL] ?? throw new RuntimeException('Missing school '.self::SECOND_SCHOOL));
        $this->yearId = (int) (DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('is_current', true)->value('id')
            ?? throw new RuntimeException('No current academic year.'));

        // Duplicate names exist (two «الزراعي» branches): the later id wins for branch-wide lookups.
        foreach (DB::table(SchemaHelper::qualified('organization', 'branches'))->where('status', 1)->orderBy('id')->get(['id', 'school_id', 'name']) as $b) {
            $this->branchIds[$b->school_id.'|'.$b->name] = (int) $b->id;
        }
        foreach (DB::table(SchemaHelper::qualified('organization', 'departments').' as d')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'd.branch_id')
            ->where('d.status', 1)->where('b.status', 1)
            // Duplicate department names (two «كهرباء»): the lowest id — the one with a curriculum — wins.
            ->orderByDesc('d.id')
            ->get(['d.id', 'd.branch_id', 'b.school_id', 'b.name as branch', 'd.name']) as $d) {
            $this->departmentIds[$d->school_id.'|'.$d->branch.'|'.$d->name] = (int) $d->id;
            $this->departmentBranch[(int) $d->id] = (int) $d->branch_id;
        }

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->schoolId]);
        foreach (DB::table(SchemaHelper::qualified('enrollment', 'classes'))->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)->get(['id', 'name']) as $c) {
            $this->classIds[$c->name] = (int) $c->id;
            foreach (DB::table(SchemaHelper::qualified('enrollment', 'sections'))->where('class_id', $c->id)->get(['id', 'name']) as $s) {
                $this->sectionIds[$c->name.'|'.$s->name] = (int) $s->id;
            }
        }

        $this->subjectIds = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->pluck('id', 'name')->map(fn ($id): int => (int) $id)->all();
    }

    private function subject(string $name): int
    {
        return $this->lookup($this->subjectIds, $name, 'subject');
    }

    /** @param  array<string, int>  $map */
    private function lookup(array $map, string $key, string $kind): int
    {
        return $map[$key] ?? throw new RuntimeException("Unknown {$kind}: {$key}");
    }

    private function expect(ApplicationResult $result, string $what): void
    {
        if ($result->failed()) {
            throw new RuntimeException("TeachersScenarioSeeder: {$what} failed — ".implode(', ', $result->errors));
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
