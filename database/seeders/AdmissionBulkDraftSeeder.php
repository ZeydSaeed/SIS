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
 * Local QA fixture: wipe admission data, then seed 200 draft applications
 * across 3 academic years and 5 named active periods.
 *
 * php artisan db:seed --class=AdmissionBulkDraftSeeder
 */
class AdmissionBulkDraftSeeder extends Seeder
{
    public const APPLICATION_PREFIX = 'APP-BULK-DRAFT-';

    public const COUNT = 200;

    /** @var list<string> */
    public const PERIOD_NAMES = [
        'دورة المستقبل',
        'فترة الخريف',
        'فترة الربيع',
        'فترة الصيف',
        'فترة التكميل',
    ];

    /** @var list<array{code: string, name: string, start: string, end: string, current: bool}> */
    public const ACADEMIC_YEARS = [
        [
            'code' => '2024-2025',
            'name' => 'السنة الدراسية 2024-2025',
            'start' => '2024-09-01',
            'end' => '2025-06-30',
            'current' => false,
        ],
        [
            'code' => '2025-2026',
            'name' => 'السنة الدراسية 2025-2026',
            'start' => '2025-09-01',
            'end' => '2026-06-30',
            'current' => false,
        ],
        [
            'code' => FoundationReference::ACADEMIC_YEAR_CODE,
            'name' => 'السنة الدراسية 2026-2027',
            'start' => '2026-09-01',
            'end' => '2027-06-30',
            'current' => true,
        ],
    ];

    public const PERIOD_MAX_APPLICATIONS = 50;

    public function run(): void
    {
        $this->call(SisFoundationSeeder::class);

        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');

        $departmentName = (string) DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('school_id', $schoolId)
            ->where('code', FoundationReference::DEPARTMENT_CODE)
            ->value('name');

        $gradeLevels = DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->orderBy('level_order')
            ->get(['id', 'name', 'code']);

        if ($schoolId < 1 || $gradeLevels->isEmpty()) {
            throw new \RuntimeException('AdmissionBulkDraftSeeder requires foundation school and grade levels.');
        }

        $this->setSchoolContext($schoolId);
        $this->purgeAdmissionData($schoolId);

        $yearIds = $this->ensureAcademicYears();
        $specializations = $this->ensureSpecializations($schoolId);
        $periodIds = $this->ensurePeriods($schoolId, $yearIds);

        if (count($periodIds) !== count(self::PERIOD_NAMES) * count(self::ACADEMIC_YEARS)) {
            throw new \RuntimeException('Expected 15 periods (5 names × 3 years).');
        }

        $countsPerPeriod = $this->distributeCounts(self::COUNT, count($periodIds));
        $sequence = 1;

        foreach ($periodIds as $periodIndex => $periodId) {
            $count = $countsPerPeriod[$periodIndex];
            for ($i = 0; $i < $count; $i++) {
                $applicant = $this->buildApplicant($sequence);
                $grade = $gradeLevels[($sequence - 1) % $gradeLevels->count()];
                $spec = $specializations[($sequence - 1) % count($specializations)];
                $number = self::APPLICATION_PREFIX.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
                $createdAt = now()
                    ->subDays(self::COUNT - $sequence)
                    ->setTime(8 + ($sequence % 8), ($sequence * 7) % 60);

                $applicationId = $this->insertApplication(
                    periodId: $periodId,
                    schoolId: $schoolId,
                    number: $number,
                    applicant: $applicant,
                    gradeLevelId: (int) $grade->id,
                    intendedGradeName: (string) $grade->name,
                    departmentName: $this->departmentLabel($sequence - 1, $departmentName),
                    specializationId: $spec['id'],
                    specializationName: $spec['name'],
                    createdAt: $createdAt->toDateTimeString(),
                );

                $this->insertDocuments($applicationId, $number, $createdAt->toDateTimeString());
                $sequence++;
            }
        }

        if ($sequence - 1 !== self::COUNT) {
            throw new \RuntimeException('Seeder did not create exactly '.self::COUNT.' applications.');
        }
    }

    /**
     * @return list<int>
     */
    private function ensureAcademicYears(): array
    {
        $now = now();
        $ids = [];

        DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('is_current', true)
            ->update(['is_current' => false, 'updated_at' => $now]);

        foreach (self::ACADEMIC_YEARS as $year) {
            $existing = DB::table(SchemaHelper::qualified('academic', 'academic_years'))
                ->where('code', $year['code'])
                ->first();

            $payload = [
                'name' => $year['name'],
                'start_date' => $year['start'],
                'end_date' => $year['end'],
                'is_current' => $year['current'],
                'status' => 1,
                'updated_at' => $now,
            ];

            if ($existing !== null) {
                DB::table(SchemaHelper::qualified('academic', 'academic_years'))
                    ->where('id', $existing->id)
                    ->update($payload);
                $ids[] = (int) $existing->id;
            } else {
                $ids[] = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))->insertGetId([
                    'code' => $year['code'],
                    ...$payload,
                    'created_at' => $now,
                ]);
            }
        }

        return $ids;
    }

    /**
     * @param  list<int>  $yearIds
     * @return list<int> Period ids ordered by year then period name index
     */
    private function ensurePeriods(int $schoolId, array $yearIds): array
    {
        $periodIds = [];

        foreach ($yearIds as $yearIndex => $yearId) {
            $yearMeta = self::ACADEMIC_YEARS[$yearIndex];
            foreach (self::PERIOD_NAMES as $periodIndex => $name) {
                $start = sprintf(
                    '%s %02d:00:00',
                    $yearMeta['start'],
                    8 + $periodIndex,
                );
                $end = sprintf(
                    '%s %02d:59:59',
                    $yearMeta['end'],
                    16 + $periodIndex,
                );

                $periodIds[] = (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
                    'academic_year_id' => $yearId,
                    'school_id' => $schoolId,
                    'name' => $name,
                    'start_date' => $start,
                    'end_date' => $end,
                    'max_applications' => self::PERIOD_MAX_APPLICATIONS,
                    'status' => ApplicationPeriodStatus::Active->value,
                    'created_at' => now()->subDays(30 - $periodIndex),
                ]);
            }
        }

        return $periodIds;
    }

    /**
     * @return list<int>
     */
    private function distributeCounts(int $total, int $buckets): array
    {
        $base = intdiv($total, $buckets);
        $remainder = $total % $buckets;
        $counts = [];

        for ($i = 0; $i < $buckets; $i++) {
            $counts[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $counts;
    }

    private function purgeAdmissionData(int $schoolId): void
    {
        $periodIds = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('school_id', $schoolId)
            ->pluck('id')
            ->all();

        if ($periodIds === []) {
            return;
        }

        $applicationIds = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->whereIn('application_period_id', $periodIds)
            ->pluck('id')
            ->all();

        if ($applicationIds !== []) {
            DB::table(SchemaHelper::qualified('admission', 'application_documents'))
                ->whereIn('application_id', $applicationIds)
                ->delete();

            DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->whereIn('id', $applicationIds)
                ->delete();
        }

        DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('school_id', $schoolId)
            ->delete();
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
    private function buildApplicant(int $sequence): array
    {
        $firstNamesMale = ['أحمد', 'محمد', 'علي', 'حسن', 'عمر', 'يوسف', 'كرار', 'حيدر', 'مصطفى', 'عباس'];
        $firstNamesFemale = ['فاطمة', 'زهراء', 'مريم', 'نورس', 'آية', 'سارة', 'هدى', 'إسراء', 'لينا', 'رنا'];
        $fathers = ['علي', 'كريم', 'جاسم', 'حسن', 'حسين', 'عبدالكريم', 'محمود', 'سعد', 'فلاح', 'قاسم'];
        $grandfathers = ['حسن', 'عباس', 'عبدالله', 'علي', 'محمد', 'فاضل', 'أحمد', 'كريم', 'حسين', 'إبراهيم'];
        $greatGrandfathers = ['محمد', 'حسين', 'صالح', 'كاظم', 'جاسم', 'ناصر', 'علي', 'عباس', 'مهدي', 'عبدالله'];
        $lastNames = ['الجبوري', 'الكاظمي', 'الدليمي', 'الموسوي', 'الشمري', 'الربيعي', 'العبيدي', 'التميمي', 'السعدي', 'النجفي'];
        $mothers = ['فاطمة', 'زينب', 'هدى', 'رقية', 'سارة', 'آمنة', 'نادية', 'إيمان', 'إسراء', 'حوراء'];
        $maternalFathers = ['كاظم', 'جواد', 'محمود', 'باقر', 'خليل', 'سعد', 'جاسم', 'عبدالرضا', 'ناظم', 'طالب'];
        $maternalGrandfathers = ['عباس', 'صادق', 'خلف', 'هادي', 'حمد', 'غانم', 'فرحان', 'سلمان', 'كاظم', 'مهدي'];
        $governorates = ['بغداد', 'الأنبار', 'النجف', 'نينوى', 'بابل', 'ديالى', 'واسط', 'ذي قار', 'كربلاء', 'البصرة'];
        $neighborhoods = ['الكرادة', 'المنصور', 'الجبيلة', 'الحر', 'العباس', 'الشموخ', 'التكية', 'الحيدرية', 'الدواسة', 'الجمعيات'];
        $birthPlaces = ['بغداد', 'الرمادي', 'النجف', 'الموصل', 'الحلة', 'بعقوبة', 'الكوت', 'الناصرية', 'كربلاء', 'البصرة'];

        $gender = $sequence % 2 === 1 ? 1 : 2;
        $firstPool = $gender === 1 ? $firstNamesMale : $firstNamesFemale;
        $i = $sequence - 1;

        $year = 2007 + ($sequence % 4);
        $month = 1 + ($sequence % 12);
        $day = 1 + ($sequence % 27);

        return [
            'first_name' => $firstPool[$i % count($firstPool)],
            'father_name' => $fathers[$i % count($fathers)],
            'grandfather_name' => $grandfathers[$i % count($grandfathers)],
            'great_grandfather_name' => $greatGrandfathers[$i % count($greatGrandfathers)],
            'last_name' => $lastNames[$i % count($lastNames)],
            'mother_name' => $mothers[$i % count($mothers)],
            'maternal_father_name' => $maternalFathers[$i % count($maternalFathers)],
            'maternal_grandfather_name' => $maternalGrandfathers[$i % count($maternalGrandfathers)],
            'national_id' => sprintf('1990%08d', 100000 + $sequence),
            'birth_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'birth_place' => $birthPlaces[$i % count($birthPlaces)],
            'gender' => $gender,
            'governorate' => $governorates[$i % count($governorates)],
            'neighborhood' => $neighborhoods[$i % count($neighborhoods)],
            'notes' => 'طلب مسودة رقم '.$sequence.' — بيانات فحص القبول',
        ];
    }

    /**
     * @param  array<string, mixed>  $applicant
     */
    private function insertApplication(
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
        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))->insertGetId([
            'application_period_id' => $periodId,
            'application_number' => $number,
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
            'created_at' => $createdAt,
            'updated_at' => now(),
        ]);
    }

    private function insertDocuments(int $applicationId, string $number, string $createdAt): void
    {
        $types = [
            1 => 'هوية.pdf',
            2 => 'شهادة-ميلاد.pdf',
            3 => 'صورة-شخصية.jpg',
        ];

        foreach ($types as $documentType => $fileName) {
            $storageKey = sprintf('qa/admission/bulk/%s/%d', $number, $documentType);
            DB::table(SchemaHelper::qualified('admission', 'application_documents'))->insert([
                'application_id' => $applicationId,
                'document_type' => $documentType,
                'storage_key' => $storageKey,
                'file_name' => $fileName,
                'file_hash' => hash('sha256', $storageKey),
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
