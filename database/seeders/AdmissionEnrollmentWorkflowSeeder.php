<?php

namespace Database\Seeders;

use App\Application\Admission\Commands\CreateApplicationDraftCommand;
use App\Application\Admission\Commands\CreateApplicationDraftHandler;
use App\Application\Admission\Commands\RegisterApplicationDocumentCommand;
use App\Application\Admission\Commands\RegisterApplicationDocumentHandler;
use App\Application\Admission\Commands\TransitionApplicationStatusCommand;
use App\Application\Admission\Commands\TransitionApplicationStatusHandler;
use App\Application\Enrollment\Commands\EnrollStudentCommand;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use Database\Seeders\Support\AdmissionCatalogReference;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Full workflow fixture: 100 unique applicants via vocational + academic-transfer
 * channels → accept/convert → enroll with catalog SSOT placement + tiny docs.
 *
 * php artisan db:seed --class=AdmissionEnrollmentWorkflowSeeder
 * php artisan sis:seed-admission-enrollment-workflow --fresh
 */
class AdmissionEnrollmentWorkflowSeeder extends Seeder
{
    public const COUNT = 100;

    public const VOCATIONAL_COUNT = 50;

    public const TRANSFER_COUNT = 50;

    public const PERIOD_NAME = 'فترة وورك فلو القبول والتسجيل';

    public const APPLICATION_PREFIX = 'APP-WF-';

    /** Document types used by admission draft dialog (11–15). */
    public const DOCUMENT_TYPES = [11, 12, 13, 14, 15];

    public function run(): void
    {
        $this->call(SisFoundationSeeder::class);

        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->value('id');
        $academicYearId = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('code', FoundationReference::ACADEMIC_YEAR_CODE)
            ->value('id');

        if ($schoolId < 1 || $academicYearId < 1) {
            throw new \RuntimeException('Workflow seeder requires foundation school + year.');
        }

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $this->purgeWorkflowData($schoolId);
        $periodId = $this->ensurePeriod($schoolId, $academicYearId);
        $placementPairs = AdmissionCatalogReference::placementPairs();
        $classes = $this->loadClasses($schoolId, $academicYearId);
        $branches = $this->loadBranches($schoolId);
        $departments = $this->loadDepartments($schoolId);
        $docBytes = $this->ensureTinyDocumentBytes();

        $createHandler = app(CreateApplicationDraftHandler::class);
        $transitionHandler = app(TransitionApplicationStatusHandler::class);
        $documentHandler = app(RegisterApplicationDocumentHandler::class);
        $enrollHandler = app(EnrollStudentHandler::class);

        $enrolled = 0;
        $converted = 0;

        for ($i = 1; $i <= self::COUNT; $i++) {
            $isTransfer = $i > self::VOCATIONAL_COUNT;
            $requestKind = $isTransfer ? 1 : 2;
            $applicant = $this->buildApplicant($i);
            $pair = $placementPairs[($i - 1) % count($placementPairs)];
            $classDef = AdmissionCatalogReference::CLASSES[($i - 1) % count(AdmissionCatalogReference::CLASSES)];
            $sectionDef = AdmissionCatalogReference::SECTIONS[($i - 1) % count(AdmissionCatalogReference::SECTIONS)];
            $branchId = $branches[$pair['branch']] ?? null;
            $departmentId = $departments[$pair['branch'].'|'.$pair['department']] ?? null;
            $classRow = $classes[$classDef['code']] ?? null;
            if ($classRow === null || $branchId === null || $departmentId === null) {
                throw new \RuntimeException("Missing placement for sequence {$i}.");
            }
            $sectionId = $classRow['sections'][$sectionDef['code']] ?? null;
            if ($sectionId === null) {
                throw new \RuntimeException("Missing section {$sectionDef['code']} for {$classDef['code']}.");
            }

            $createResult = $createHandler->handle(new CreateApplicationDraftCommand(
                schoolId: $schoolId,
                applicationPeriodId: $periodId,
                firstName: $applicant['first_name'],
                fatherName: $applicant['father_name'],
                grandfatherName: $applicant['grandfather_name'],
                greatGrandfatherName: $applicant['great_grandfather_name'],
                lastName: $applicant['last_name'],
                motherName: $applicant['mother_name'],
                maternalFatherName: $applicant['maternal_father_name'],
                maternalGrandfatherName: $applicant['maternal_grandfather_name'],
                birthDate: $applicant['birth_date'],
                birthPlace: $applicant['birth_place'],
                gender: $applicant['gender'],
                targetSchoolId: $schoolId,
                intendedGradeName: $classDef['name'],
                requestKind: $requestKind,
                nationalId: $applicant['national_id'],
                gradeLevelId: $classRow['grade_level_id'],
                branchId: $branchId,
                branchName: $pair['branch'],
                departmentName: $pair['department'],
                governorate: $applicant['governorate'],
                administrativeUnit: $applicant['administrative_unit'],
                neighborhood: $applicant['neighborhood'],
                fatherOccupation: $applicant['father_occupation'],
                motherOccupation: $applicant['mother_occupation'],
                studentMobile: $applicant['student_mobile'],
                guardianMobile: $applicant['guardian_mobile'],
                previousSchoolName: $isTransfer ? $applicant['previous_school_name'] : null,
                graduationYear: $isTransfer ? $applicant['graduation_year'] : null,
                previousGpa: $isTransfer ? $applicant['previous_gpa'] : null,
                mathematicsGrade: $isTransfer ? $applicant['mathematics_grade'] : null,
                physicsGrade: $isTransfer ? $applicant['physics_grade'] : null,
                previousStudyTrack: $isTransfer ? $applicant['previous_study_track'] : null,
                notes: $isTransfer ? 'وورك فلو — تحويل أكاديمي→مهني' : 'وورك فلو — قبول مهني جديد',
                idempotencyKey: 'wf-create-'.$i,
            ));

            $applicationId = $createResult->applicationId;

            foreach ([
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderReview,
                ApplicationStatus::Accepted,
            ] as $toStatus) {
                $transitionHandler->handle(new TransitionApplicationStatusCommand(
                    schoolId: $schoolId,
                    applicationId: $applicationId,
                    toStatus: $toStatus->value,
                    reviewedBy: null,
                    notes: null,
                    idempotencyKey: 'wf-tr-'.$i.'-'.$toStatus->value,
                ));
            }

            foreach (self::DOCUMENT_TYPES as $docType) {
                $fileName = sprintf('wf-%03d-doc-%d.png', $i, $docType);
                $storageKey = sprintf('admission/%d/%s', $applicationId, $fileName);
                Storage::disk('local')->put($storageKey, $docBytes);
                $documentHandler->handle(new RegisterApplicationDocumentCommand(
                    schoolId: $schoolId,
                    applicationId: $applicationId,
                    documentType: $docType,
                    fileName: $fileName,
                    storageKey: $storageKey,
                    fileHash: hash('sha256', $docBytes),
                    idempotencyKey: 'wf-doc-'.$i.'-'.$docType,
                ));
            }

            $studentId = (int) DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->where('id', $applicationId)
                ->value('student_id');
            if ($studentId < 1) {
                throw new \RuntimeException("Application {$applicationId} was not converted to a student.");
            }
            $converted++;

            $enrollHandler->handle(new EnrollStudentCommand(
                schoolId: $schoolId,
                academicYearId: $academicYearId,
                studentId: $studentId,
                classId: $classRow['id'],
                sectionId: $sectionId,
                effectiveFrom: '2026-09-01',
                specializationId: null,
                branchId: $branchId,
                departmentId: $departmentId,
                enrolledBy: null,
                idempotencyKey: 'wf-enroll-'.$i,
            ));
            $enrolled++;

            if ($i % 20 === 0) {
                $this->command?->info("Workflow progress: {$i}/".self::COUNT);
            }
        }

        $this->assertWorkflowComplete($schoolId, $academicYearId, $converted, $enrolled);
        $this->command?->info("Workflow complete: {$converted} students, {$enrolled} enrollments.");
    }

    private function purgeWorkflowData(int $schoolId): void
    {
        $periodIds = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('school_id', $schoolId)
            ->where('name', self::PERIOD_NAME)
            ->pluck('id')
            ->all();

        if ($periodIds === []) {
            return;
        }

        $appIds = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->whereIn('application_period_id', $periodIds)
            ->pluck('id')
            ->all();

        if ($appIds !== []) {
            $studentIds = DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->whereIn('id', $appIds)
                ->whereNotNull('student_id')
                ->pluck('student_id')
                ->all();

            DB::table(SchemaHelper::qualified('admission', 'application_documents'))
                ->whereIn('application_id', $appIds)
                ->delete();

            if ($studentIds !== []) {
                DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
                    ->whereIn('student_id', $studentIds)
                    ->where('status', 1)
                    ->update([
                        'status' => 5,
                        'effective_to' => now()->toDateString(),
                        'updated_at' => now(),
                    ]);

                DB::table(SchemaHelper::qualified('admission', 'applications'))
                    ->whereIn('id', $appIds)
                    ->update(['student_id' => null, 'updated_at' => now()]);

                foreach ($studentIds as $studentId) {
                    $token = substr(md5('wf'.$studentId.microtime(true)), 0, 12);
                    DB::table(SchemaHelper::qualified('students', 'students'))
                        ->where('id', $studentId)
                        ->update([
                            'status' => 0,
                            'national_id' => 'A'.$token,
                            'student_code' => 'Z'.$token,
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::table(SchemaHelper::qualified('admission', 'applications'))
                ->whereIn('id', $appIds)
                ->delete();
        }

        DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->whereIn('id', $periodIds)
            ->delete();
    }

    private function ensurePeriod(int $schoolId, int $academicYearId): int
    {
        $existing = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('name', self::PERIOD_NAME)
            ->value('id');
        if ($existing !== null) {
            DB::table(SchemaHelper::qualified('admission', 'application_periods'))
                ->where('id', $existing)
                ->update([
                    'status' => ApplicationPeriodStatus::Active->value,
                    'max_applications' => self::COUNT + 20,
                    'start_date' => '2026-08-01 00:00:00',
                    'end_date' => '2027-07-01 23:59:59',
                ]);

            return (int) $existing;
        }

        return (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
            'academic_year_id' => $academicYearId,
            'school_id' => $schoolId,
            'name' => self::PERIOD_NAME,
            'start_date' => '2026-08-01 00:00:00',
            'end_date' => '2027-07-01 23:59:59',
            'max_applications' => self::COUNT + 20,
            'status' => ApplicationPeriodStatus::Active->value,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, array{id:int, grade_level_id:int, sections: array<string,int>}>
     */
    private function loadClasses(int $schoolId, int $academicYearId): array
    {
        $rows = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->get(['id', 'code', 'grade_level_id']);

        $map = [];
        foreach ($rows as $row) {
            $sections = DB::table(SchemaHelper::qualified('enrollment', 'sections'))
                ->where('class_id', $row->id)
                ->pluck('id', 'code')
                ->map(static fn ($id): int => (int) $id)
                ->all();
            $map[(string) $row->code] = [
                'id' => (int) $row->id,
                'grade_level_id' => (int) $row->grade_level_id,
                'sections' => $sections,
            ];
        }

        return $map;
    }

    /** @return array<string, int> */
    private function loadBranches(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $schoolId)
            ->pluck('id', 'name')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<string, int> key = branch|department */
    private function loadDepartments(int $schoolId): array
    {
        $rows = DB::table(SchemaHelper::qualified('organization', 'departments').' as d')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'd.branch_id')
            ->where('d.school_id', $schoolId)
            ->get(['d.id', 'd.name as department_name', 'b.name as branch_name']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->branch_name.'|'.$row->department_name] = (int) $row->id;
        }

        return $map;
    }

    /**
     * ~10KB PNG so 100 images ≈ 1MB.
     */
    private function ensureTinyDocumentBytes(): string
    {
        $path = storage_path('app/seed/wf-doc-sample.png');
        if (is_file($path) && filesize($path) > 8000) {
            return (string) file_get_contents($path);
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        // ~220×220 noise ≈ 10KB PNG so 100 images ≈ 1MB.
        $size = 220;
        $image = imagecreatetruecolor($size, $size);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $tone = 40 + (($x * 7 + $y * 13) % 180);
                $color = imagecolorallocate($image, $tone, $tone + 8, $tone + 16);
                imagesetpixel($image, $x, $y, $color);
            }
        }
        $label = imagecolorallocate($image, 245, 247, 250);
        imagestring($image, 5, 70, 100, 'SIS DOC', $label);
        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($image);
        file_put_contents($path, $png);

        return $png;
    }

    /**
     * @return array{
     *     first_name:string,father_name:string,grandfather_name:string,great_grandfather_name:string,
     *     last_name:string,mother_name:string,maternal_father_name:string,maternal_grandfather_name:string,
     *     national_id:string,birth_date:string,birth_place:string,gender:int,governorate:string,
     *     administrative_unit:int,neighborhood:string,father_occupation:string,mother_occupation:string,
     *     student_mobile:string,guardian_mobile:string,previous_school_name:string,graduation_year:int,
     *     previous_gpa:string,mathematics_grade:string,physics_grade:string,previous_study_track:int
     * }
     */
    private function buildApplicant(int $sequence): array
    {
        $first = [
            'أحمد', 'علي', 'حسن', 'حسين', 'محمد', 'يوسف', 'كريم', 'سامر', 'باسم', 'رائد',
            'فاطمة', 'زينب', 'مريم', 'هدى', 'سارة', 'نور', 'إسراء', 'آية', 'رغد', 'لينا',
        ];
        $middle = [
            'جواد', 'كاظم', 'عباس', 'جاسم', 'طالب', 'ناصر', 'ماجد', 'فؤاد', 'رياض', 'سعد',
            'خليل', 'جبار', 'فاضل', 'منذر', 'وائل', 'عماد', 'طه', 'رزاق', 'قاسم', 'سجاد',
        ];
        $last = [
            'التميمي', 'الجبوري', 'العامري', 'الحسيني', 'الساعدي', 'الشمري', 'الدليمي', 'الزبيدي',
            'الكعبي', 'الموسوي', 'الخفاجي', 'الربيعي', 'الطائي', 'الأنصاري', 'القرشي',
        ];

        // Unique full name: combine pools with sequence so no two rows collide.
        $f = $first[($sequence - 1) % count($first)];
        $fa = $middle[($sequence - 1) % count($middle)];
        $gf = $middle[$sequence % count($middle)];
        $gg = $middle[($sequence + 3) % count($middle)];
        $ln = $last[($sequence - 1) % count($last)].'-'.$sequence;
        $mother = $first[($sequence + 7) % count($first)];
        $mf = $middle[($sequence + 5) % count($middle)];
        $mg = $middle[($sequence + 9) % count($middle)];

        $day = (($sequence - 1) % 28) + 1;
        $month = (($sequence - 1) % 12) + 1;
        $year = 2007 + (($sequence - 1) % 4);

        return [
            'first_name' => $f,
            'father_name' => $fa,
            'grandfather_name' => $gf,
            'great_grandfather_name' => $gg,
            'last_name' => $ln,
            'mother_name' => $mother,
            'maternal_father_name' => $mf,
            'maternal_grandfather_name' => $mg,
            'national_id' => sprintf('199%09d', 100000000 + $sequence),
            'birth_date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'birth_place' => 'محلة الولادة-'.$sequence,
            'gender' => $sequence % 2 === 0 ? 2 : 1,
            'governorate' => 'محافظة-'.$sequence,
            'administrative_unit' => (($sequence - 1) % 3) + 1,
            'neighborhood' => 'حي-'.$sequence,
            'father_occupation' => 'مهنة أب-'.$sequence,
            'mother_occupation' => 'مهنة أم-'.$sequence,
            'student_mobile' => sprintf('0770%07d', 1000000 + $sequence),
            'guardian_mobile' => sprintf('0780%07d', 2000000 + $sequence),
            'previous_school_name' => 'متوسطة سابقة-'.$sequence,
            'graduation_year' => 2024 + ($sequence % 2),
            'previous_gpa' => sprintf('%.2f', 70 + ($sequence % 30) + (($sequence % 10) / 10)),
            'mathematics_grade' => sprintf('%.2f', 60 + ($sequence % 40)),
            'physics_grade' => sprintf('%.2f', 55 + ($sequence % 45)),
            'previous_study_track' => (($sequence - 1) % 6) + 1,
        ];
    }

    private function assertWorkflowComplete(
        int $schoolId,
        int $academicYearId,
        int $converted,
        int $enrolled,
    ): void {
        if ($converted !== self::COUNT || $enrolled !== self::COUNT) {
            throw new \RuntimeException("Expected ".self::COUNT." converted/enrolled, got {$converted}/{$enrolled}.");
        }

        $students = (int) DB::table(SchemaHelper::qualified('students', 'students'))
            ->where('school_id', $schoolId)
            ->count();
        $enrollments = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 1)
            ->count();
        $docs = (int) DB::table(SchemaHelper::qualified('admission', 'application_documents').' as d')
            ->join(SchemaHelper::qualified('admission', 'applications').' as a', 'a.id', '=', 'd.application_id')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.school_id', $schoolId)
            ->where('a.application_number', 'like', self::APPLICATION_PREFIX.'%')
            ->count();

        // Applications use generated numbers from repository, not our prefix — count by notes marker.
        $apps = (int) DB::table(SchemaHelper::qualified('admission', 'applications').' as a')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.school_id', $schoolId)
            ->where('p.name', self::PERIOD_NAME)
            ->count();

        if ($apps < self::COUNT || $students < self::COUNT || $enrollments < self::COUNT) {
            throw new \RuntimeException(
                "Workflow assertion failed: apps={$apps}, students={$students}, enrollments={$enrollments}, docs={$docs}",
            );
        }

        $vocational = (int) DB::table(SchemaHelper::qualified('admission', 'applications').' as a')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.name', self::PERIOD_NAME)
            ->where('a.request_kind', 2)
            ->count();
        $transfer = (int) DB::table(SchemaHelper::qualified('admission', 'applications').' as a')
            ->join(SchemaHelper::qualified('admission', 'application_periods').' as p', 'p.id', '=', 'a.application_period_id')
            ->where('p.name', self::PERIOD_NAME)
            ->where('a.request_kind', 1)
            ->count();

        if ($vocational !== self::VOCATIONAL_COUNT || $transfer !== self::TRANSFER_COUNT) {
            throw new \RuntimeException("Channel split failed: vocational={$vocational}, transfer={$transfer}");
        }
    }
}
