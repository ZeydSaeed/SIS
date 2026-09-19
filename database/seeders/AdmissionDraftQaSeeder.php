<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Domain\Vocational\ValueObjects\VocationalCatalogStatus;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local QA fixture: 20 complete draft admission applications for drafts-page inspection.
 * Opt-in only — not called from DatabaseSeeder.
 *
 * php artisan db:seed --class=AdmissionDraftQaSeeder
 */
class AdmissionDraftQaSeeder extends Seeder
{
    public const PERIOD_NAME = 'فترة فحص المسودات';

    public const APPLICATION_PREFIX = 'APP-QA-DRAFT-';

    public const COUNT = 20;

    public function run(): void
    {
        $this->call(SisFoundationSeeder::class);

        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');
        $academicYearId = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('code', FoundationReference::ACADEMIC_YEAR_CODE)
            ->value('id');
        $departmentName = (string) DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('school_id', $schoolId)
            ->where('code', FoundationReference::DEPARTMENT_CODE)
            ->value('name');

        $gradeLevels = DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->orderBy('level_order')
            ->get(['id', 'name', 'code']);

        if ($schoolId < 1 || $academicYearId < 1 || $gradeLevels->isEmpty()) {
            throw new \RuntimeException('AdmissionDraftQaSeeder requires foundation school, year, and grade levels.');
        }

        $this->setSchoolContext($schoolId);

        $specializations = $this->ensureSpecializations($schoolId);
        $periodId = $this->ensurePeriod($schoolId, $academicYearId);

        foreach ($this->applicants() as $index => $applicant) {
            $grade = $gradeLevels[$index % $gradeLevels->count()];
            $spec = $specializations[$index % count($specializations)];
            $number = self::APPLICATION_PREFIX.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
            $createdAt = now()->subDays(20 - $index)->setTime(9 + ($index % 6), 15 + ($index % 4) * 10);

            $applicationId = $this->upsertApplication(
                periodId: $periodId,
                schoolId: $schoolId,
                number: $number,
                applicant: $applicant,
                gradeLevelId: (int) $grade->id,
                intendedGradeName: (string) $grade->name,
                departmentName: $this->departmentLabel($index, $departmentName),
                specializationId: $spec['id'],
                specializationName: $spec['name'],
                createdAt: $createdAt->toDateTimeString(),
            );

            $this->ensureDocuments($applicationId, $number, $createdAt->toDateTimeString());
        }
    }

    /**
     * @return list<array{
     *     first_name: string,
     *     father_name: string,
     *     grandfather_name: string,
     *     great_grandfather_name: string,
     *     last_name: string,
     *     mother_name: string,
     *     maternal_father_name: string,
     *     maternal_grandfather_name: string,
     *     national_id: string,
     *     birth_date: string,
     *     birth_place: string,
     *     gender: int,
     *     governorate: string,
     *     neighborhood: string,
     *     notes: string
     * }>
     */
    private function applicants(): array
    {
        return [
            $this->applicant('أحمد', 'علي', 'حسن', 'محمد', 'الجبوري', 'فاطمة', 'كاظم', 'عباس', '199012345001', '2008-03-12', 'بغداد', 1, 'بغداد', 'الكرادة', 'مسودة فحص — مستمسكات مكتملة'),
            $this->applicant('فاطمة', 'كريم', 'عباس', 'حسين', 'الكاظمي', 'زينب', 'جواد', 'صادق', '199012345002', '2009-07-21', 'الكاظمية', 2, 'بغداد', 'الكاظمية', 'تأكيد عنوان السكن مع الطلب'),
            $this->applicant('محمد', 'جاسم', 'عبدالله', 'صالح', 'الدليمي', 'هدى', 'محمود', 'خلف', '199012345003', '2008-11-04', 'الرمادي', 1, 'الأنبار', 'الرمادي المركز', 'يرغب بالاختصاص الصناعي'),
            $this->applicant('زهراء', 'حسن', 'علي', 'كاظم', 'الموسوي', 'رقية', 'باقر', 'هادي', '199012345004', '2009-01-18', 'النجف', 2, 'النجف', 'حي الأمير', 'ملف مدني مكتمل'),
            $this->applicant('علي', 'حسين', 'محمد', 'جاسم', 'الشمري', 'سارة', 'خليل', 'حمد', '199012345005', '2008-05-09', 'الموصل', 1, 'نينوى', 'الزعفراني', 'يحتاج مراجعة لاحقة للملاحظات'),
            $this->applicant('مريم', 'عبدالكريم', 'فاضل', 'ناصر', 'الربيعي', 'آمنة', 'سعد', 'غانم', '199012345006', '2009-09-30', 'الحلة', 2, 'بابل', 'الجمعيات', 'طلب مسودة لأغراض الفحص'),
            $this->applicant('حسن', 'محمود', 'أحمد', 'علي', 'العبيدي', 'نادية', 'جاسم', 'فرحان', '199012345007', '2008-02-14', 'بعقوبة', 1, 'ديالى', 'التكية', 'البيانات المدنية مطابقة للهوية'),
            $this->applicant('نورس', 'سعد', 'كريم', 'محمد', 'التميمي', 'إيمان', 'عبدالرضا', 'سلمان', '199012345008', '2009-12-02', 'الكوت', 2, 'واسط', 'الحيدرية', 'موافقة ولي الأمر مرفقة'),
            $this->applicant('مصطفى', 'فلاح', 'حسن', 'عباس', 'السعدي', 'إسراء', 'ناظم', 'كاظم', '199012345009', '2008-08-25', 'الناصرية', 1, 'ذي قار', 'الشموخ', 'اختصاص ميكانيك مفضل'),
            $this->applicant('آية', 'قاسم', 'علي', 'حسين', 'النجفي', 'حوراء', 'طالب', 'مهدي', '199012345010', '2009-04-11', 'كربلاء', 2, 'كربلاء', 'الحر', 'مسودة كاملة بدون نواقص'),
            $this->applicant('عمر', 'خليل', 'إبراهيم', 'محمد', 'الأنصاري', 'ليلى', 'ماجد', 'ياسين', '199012345011', '2008-06-19', 'البصرة', 1, 'البصرة', 'الجبيلة', 'سكن قريب من المدرسة'),
            $this->applicant('سارة', 'وليد', 'عبدالرزاق', 'جاسم', 'البصري', 'دنيا', 'فوزي', 'رشيد', '199012345012', '2009-10-07', 'أبو الخصيب', 2, 'البصرة', 'أبو الخصيب', 'تم تدقيق رقم الهوية'),
            $this->applicant('يوسف', 'طارق', 'نعمة', 'كاظم', 'الحلي', 'بتول', 'عادل', 'حمزة', '199012345013', '2008-01-28', 'الحلة', 1, 'بابل', 'الإسكان', 'يرغب بالصف العاشر'),
            $this->applicant('هدى', 'باسم', 'محمد', 'علي', 'الكربلائي', 'ملاك', 'حسين', 'جواد', '199012345014', '2009-03-16', 'كربلاء', 2, 'كربلاء', 'العباس', 'مستمسكات الهوية والميلاد والصورة'),
            $this->applicant('كرار', 'حيدر', 'صالح', 'مهدي', 'الواسطي', 'ضحى', 'كاظم', 'عبدالأمير', '199012345015', '2008-09-03', 'الكوت', 1, 'واسط', 'الكرامة', 'فحص جدول المسودات'),
            $this->applicant('إسراء', 'عادل', 'حسين', 'علي', 'الديواني', 'غادة', 'ماجد', 'شاكر', '199012345016', '2009-05-22', 'الديوانية', 2, 'القادسية', 'العروبة', 'بيانات الأم مكتملة'),
            $this->applicant('حيدر', 'نصير', 'جاسم', 'محمد', 'الأنباري', 'رنا', 'صبحي', 'لطيف', '199012345017', '2008-12-13', 'الفلوجة', 1, 'الأنبار', 'الفلوجة', 'مسودة جاهزة للتحويل لاحقاً'),
            $this->applicant('لينا', 'فؤاد', 'كريم', 'حسن', 'الموصلي', 'هند', 'ياسر', 'نوري', '199012345018', '2009-08-08', 'الموصل', 2, 'نينوى', 'الدواسة', 'عنوان الحي محدث'),
            $this->applicant('عباس', 'سلام', 'علي', 'حسين', 'الناصري', 'وفاء', 'عبدالوهاب', 'راضي', '199012345019', '2008-04-27', 'الشطرة', 1, 'ذي قار', 'الشطرة', 'ملاحظات الفحص الفني'),
            $this->applicant('رنا', 'مازن', 'عبدالله', 'محمد', 'البغدادي', 'شيماء', 'خالد', 'إبراهيم', '199012345020', '2009-02-05', 'بغداد', 2, 'بغداد', 'المنصور', 'طلب مسودة رقم عشرين'),
        ];
    }

    /**
     * @return array{
     *     first_name: string,
     *     father_name: string,
     *     grandfather_name: string,
     *     great_grandfather_name: string,
     *     last_name: string,
     *     mother_name: string,
     *     maternal_father_name: string,
     *     maternal_grandfather_name: string,
     *     national_id: string,
     *     birth_date: string,
     *     birth_place: string,
     *     gender: int,
     *     governorate: string,
     *     neighborhood: string,
     *     notes: string
     * }
     */
    private function applicant(
        string $firstName,
        string $fatherName,
        string $grandfatherName,
        string $greatGrandfatherName,
        string $lastName,
        string $motherName,
        string $maternalFatherName,
        string $maternalGrandfatherName,
        string $nationalId,
        string $birthDate,
        string $birthPlace,
        int $gender,
        string $governorate,
        string $neighborhood,
        string $notes,
    ): array {
        return [
            'first_name' => $firstName,
            'father_name' => $fatherName,
            'grandfather_name' => $grandfatherName,
            'great_grandfather_name' => $greatGrandfatherName,
            'last_name' => $lastName,
            'mother_name' => $motherName,
            'maternal_father_name' => $maternalFatherName,
            'maternal_grandfather_name' => $maternalGrandfatherName,
            'national_id' => $nationalId,
            'birth_date' => $birthDate,
            'birth_place' => $birthPlace,
            'gender' => $gender,
            'governorate' => $governorate,
            'neighborhood' => $neighborhood,
            'notes' => $notes,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function ensureSpecializations(int $schoolId): array
    {
        $now = now();
        $catalog = [
            ['code' => 'QA-ELEC', 'name' => 'الكهرباء'],
            ['code' => 'QA-MECH', 'name' => 'الميكانيك'],
            ['code' => 'QA-IT', 'name' => 'الحاسوب'],
        ];

        $rows = [];
        foreach ($catalog as $item) {
            $existing = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
                ->where('school_id', $schoolId)
                ->where('code', $item['code'])
                ->first();

            if ($existing !== null) {
                DB::table(SchemaHelper::qualified('vocational', 'specializations'))
                    ->where('id', $existing->id)
                    ->update([
                        'name' => $item['name'],
                        'status' => VocationalCatalogStatus::Active->value,
                        'updated_at' => $now,
                    ]);
                $id = (int) $existing->id;
            } else {
                $id = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
                    'school_id' => $schoolId,
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'description' => 'اختصاص فحص القبول',
                    'status' => VocationalCatalogStatus::Active->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $rows[] = ['id' => $id, 'name' => $item['name']];
        }

        return $rows;
    }

    private function ensurePeriod(int $schoolId, int $academicYearId): int
    {
        $existing = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('name', self::PERIOD_NAME)
            ->first();

        $payload = [
            'academic_year_id' => $academicYearId,
            'school_id' => $schoolId,
            'name' => self::PERIOD_NAME,
            'start_date' => '2026-09-01 00:00:00',
            'end_date' => '2027-06-30 23:59:59',
            'max_applications' => 100,
            'status' => ApplicationPeriodStatus::Active->value,
        ];

        if ($existing !== null) {
            DB::table(SchemaHelper::qualified('admission', 'application_periods'))
                ->where('id', $existing->id)
                ->update($payload);

            return (int) $existing->id;
        }

        return (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
            ...$payload,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $applicant
     */
    private function upsertApplication(
        int $periodId,
        int $schoolId,
        string $number,
        array $applicant,
        int $gradeLevelId,
        string $intendedGradeName,
        string $departmentName,
        int $specializationId,
        string $specializationName,
        string $createdAt,
    ): int {
        $payload = [
            'application_period_id' => $periodId,
            'first_name' => $applicant['first_name'],
            'father_name' => $applicant['father_name'],
            'grandfather_name' => $applicant['grandfather_name'],
            'great_grandfather_name' => $applicant['great_grandfather_name'],
            'last_name' => $applicant['last_name'],
            'mother_name' => $applicant['mother_name'],
            'maternal_father_name' => $applicant['maternal_father_name'],
            'maternal_grandfather_name' => $applicant['maternal_grandfather_name'],
            'national_id' => $applicant['national_id'],
            'birth_date' => $applicant['birth_date'],
            'birth_place' => $applicant['birth_place'],
            'gender' => $applicant['gender'],
            'target_school_id' => $schoolId,
            'grade_level_id' => $gradeLevelId,
            'intended_grade_name' => $intendedGradeName,
            'department_name' => $departmentName,
            'specialization_id' => $specializationId,
            'specialization_name' => $specializationName,
            'governorate' => $applicant['governorate'],
            'neighborhood' => $applicant['neighborhood'],
            'status' => ApplicationStatus::Draft->value,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => $applicant['notes'],
            'student_id' => null,
            'updated_at' => now(),
        ];

        $existingId = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('application_number', $number)
            ->value('id');

        if ($existingId !== null) {
            DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->where('id', $existingId)
                ->update($payload);

            return (int) $existingId;
        }

        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))->insertGetId([
            ...$payload,
            'application_number' => $number,
            'created_at' => $createdAt,
        ]);
    }

    private function ensureDocuments(int $applicationId, string $number, string $createdAt): void
    {
        $types = [
            1 => 'هوية.pdf',
            2 => 'شهادة-ميلاد.pdf',
            3 => 'صورة-شخصية.jpg',
        ];

        foreach ($types as $documentType => $fileName) {
            $storageKey = sprintf('qa/admission/drafts/%s/%d', $number, $documentType);
            $hash = hash('sha256', $storageKey);
            $existing = DB::table(SchemaHelper::qualified('admission', 'application_documents'))
                ->where('application_id', $applicationId)
                ->where('document_type', $documentType)
                ->first();

            $payload = [
                'storage_key' => $storageKey,
                'file_name' => $fileName,
                'file_hash' => $hash,
            ];

            if ($existing !== null) {
                DB::table(SchemaHelper::qualified('admission', 'application_documents'))
                    ->where('id', $existing->id)
                    ->update($payload);

                continue;
            }

            DB::table(SchemaHelper::qualified('admission', 'application_documents'))->insert([
                'application_id' => $applicationId,
                'document_type' => $documentType,
                ...$payload,
                'created_at' => $createdAt,
            ]);
        }
    }

    private function departmentLabel(int $index, string $fallback): string
    {
        $labels = ['الكهرباء', 'الميكانيك', 'الحاسوب'];

        return $labels[$index % count($labels)] ?? $fallback;
    }

    private function setSchoolContext(int $schoolId): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
