<?php

namespace Database\Seeders;

use App\Application\Admission\Commands\CreateApplicationDraftCommand;
use App\Application\Admission\Commands\CreateApplicationDraftHandler;
use App\Application\Admission\Commands\RegisterApplicationDocumentCommand;
use App\Application\Admission\Commands\RegisterApplicationDocumentHandler;
use App\Application\Admission\Commands\RegisterStudentViaAdmissionCommand;
use App\Application\Admission\Commands\RegisterStudentViaAdmissionHandler;
use App\Application\Admission\Commands\TransitionApplicationStatusCommand;
use App\Application\Admission\Commands\TransitionApplicationStatusHandler;
use App\Application\Curriculum\Commands\AddSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\AddSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\CreateCurriculumCommand;
use App\Application\Curriculum\Commands\CreateCurriculumHandler;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectCommand;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectHandler;
use App\Application\Enrollment\Commands\AssignEnrollmentSubjectCommand;
use App\Application\Enrollment\Commands\AssignEnrollmentSubjectHandler;
use App\Application\Enrollment\Commands\BulkEnrollStudentsCommand;
use App\Application\Enrollment\Commands\BulkEnrollStudentsHandler;
use App\Application\Enrollment\Commands\CancelEnrollmentCommand;
use App\Application\Enrollment\Commands\CancelEnrollmentHandler;
use App\Application\Enrollment\Commands\ChangeEnrollmentStatusesCommand;
use App\Application\Enrollment\Commands\ChangeEnrollmentStatusesHandler;
use App\Application\Student\Commands\ChangeStudentStatusesCommand;
use App\Application\Student\Commands\ChangeStudentStatusesHandler;
use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Infrastructure\Jobs\ProcessOutboxJob;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Security\Context\SchoolContextScope;
use Database\Seeders\Support\AdmissionCatalogReference;
use Database\Seeders\Support\CurriculumSubjectCatalogReference;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Workflow scenarios through the real application handlers (admission → student →
 * enrollment → curriculum). 130 applicants: 30 stay at admission stages and 100
 * become students in every post-admission state the system supports.
 *
 * Run via: php artisan sis:seed-workflow-scenarios --truncate
 */
class WorkflowScenarioSeeder extends Seeder
{
    public const PERIOD_NAME = 'فترة القبول 2026-2027';

    public const CLOSED_PERIOD_NAME = 'فترة القبول المبكر (مؤرشفة)';

    /** Admission-only outcomes (no student record). */
    private const ADMISSION_ONLY = [
        'draft' => 6,
        'submitted' => 5,
        'under_review' => 5,
        'interview' => 4,
        'waitlisted' => 4,
        'rejected' => 3,
        'withdrawn' => 3,
    ];

    /** Student outcomes — 100 students in total. */
    private const STUDENT_OUTCOMES = [
        'enrolled' => 70,
        'enrolled_cancelled' => 4,
        'enrolled_suspended' => 2,
        'enrolled_withdrawn' => 2,
        'awaiting_enrollment' => 11,
        'capacity_blocked' => 3,
        'suspended' => 3,
        'withdrawn_student' => 2,
        'registered_direct' => 3,
    ];

    /** CLS-3 / SEC-C is the capacity demo section (2 seats, filled first). */
    private const CAPACITY_CLASS = 'CLS-3';

    private const CAPACITY_SECTION = 'SEC-C';

    private const CAPACITY_SEATS = 2;

    private const DOCUMENT_TYPES = [11, 12, 13, 14, 15];

    private int $schoolId;

    private int $yearId;

    private int $periodId;

    /** @var array<string, array{id:int, grade_level_id:int, sections: array<string,int>}> */
    private array $classes = [];

    /** @var array<string, int> */
    private array $branches = [];

    /** @var array<string, int> key = branch|department */
    private array $departments = [];

    /** @var array<int, int> department id → its branch id (branch names may repeat on legacy data) */
    private array $departmentBranch = [];

    /** @var array<string, int> subject name → id */
    private array $subjects = [];

    private string $docBytes = '';

    /** @var array<string, int> */
    public array $summary = [];

    public function run(): void
    {
        $this->call([
            FoundationOrganizationSeeder::class,
            FoundationAcademicSeeder::class,
            FoundationEnrollmentStructureSeeder::class,
            CurriculumCatalogSeeder::class,
        ]);

        $this->schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)->value('id');
        $this->yearId = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('code', FoundationReference::ACADEMIC_YEAR_CODE)->value('id');
        if ($this->schoolId < 1 || $this->yearId < 1) {
            throw new \RuntimeException('Scenario seeder requires the foundation school and academic year.');
        }

        app(SchoolContextScope::class)->run($this->schoolId, function (): void {
            $this->loadCatalog();
            $this->prepareCapacityDemo();
            $this->periodId = $this->createPeriods();
            $this->createCurricula();
            $this->createPrerequisites();
            $this->runAdmissionsAndEnrollments();
        });
    }

    // ---------------------------------------------------------------- catalog

    private function loadCatalog(): void
    {
        foreach (DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $this->schoolId)->where('academic_year_id', $this->yearId)
            ->get(['id', 'code', 'grade_level_id']) as $row) {
            $this->classes[(string) $row->code] = [
                'id' => (int) $row->id,
                'grade_level_id' => (int) $row->grade_level_id,
                'sections' => DB::table(SchemaHelper::qualified('enrollment', 'sections'))
                    ->where('class_id', $row->id)->pluck('id', 'code')
                    ->map(static fn ($id): int => (int) $id)->all(),
            ];
        }

        $this->branches = DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $this->schoolId)->pluck('id', 'name')
            ->map(static fn ($id): int => (int) $id)->all();

        foreach (DB::table(SchemaHelper::qualified('organization', 'departments').' as d')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'd.branch_id')
            ->where('d.school_id', $this->schoolId)
            ->orderBy('d.id')
            ->get(['d.id', 'd.branch_id', 'd.name as department', 'b.name as branch']) as $row) {
            $this->departments[$row->branch.'|'.$row->department] ??= (int) $row->id;
            $this->departmentBranch[(int) $row->id] = (int) $row->branch_id;
        }

        $this->subjects = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('status', 1)->pluck('id', 'name')
            ->map(static fn ($id): int => (int) $id)->all();

        $this->docBytes = $this->tinyPng();
    }

    private function prepareCapacityDemo(): void
    {
        $sectionId = $this->classes[self::CAPACITY_CLASS]['sections'][self::CAPACITY_SECTION] ?? null;
        if ($sectionId === null) {
            throw new \RuntimeException('Capacity demo section missing.');
        }
        DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('id', $sectionId)->update(['capacity' => self::CAPACITY_SEATS, 'updated_at' => now()]);
    }

    private function createPeriods(): int
    {
        $table = SchemaHelper::qualified('admission', 'application_periods');
        DB::table($table)->insert([
            'academic_year_id' => $this->yearId,
            'school_id' => $this->schoolId,
            'name' => self::CLOSED_PERIOD_NAME,
            'start_date' => '2026-05-01 00:00:00',
            'end_date' => '2026-06-30 23:59:59',
            'max_applications' => 50,
            'status' => ApplicationPeriodStatus::Archived->value,
            'created_at' => now(),
        ]);

        return (int) DB::table($table)->insertGetId([
            'academic_year_id' => $this->yearId,
            'school_id' => $this->schoolId,
            'name' => self::PERIOD_NAME,
            'start_date' => '2026-08-01 00:00:00',
            'end_date' => '2027-07-01 23:59:59',
            'max_applications' => 200,
            'status' => ApplicationPeriodStatus::Active->value,
            'created_at' => now(),
        ]);
    }

    // ------------------------------------------------------------- curricula

    /** One curriculum per (branch → الاختصاص) × class; electives linked as optional. */
    private function createCurricula(): void
    {
        $create = app(CreateCurriculumHandler::class);
        $link = app(LinkCurriculumSubjectHandler::class);
        $count = 0;

        foreach (AdmissionCatalogReference::placementPairs() as $pair) {
            $departmentId = $this->departments[$pair['branch'].'|'.$pair['department']] ?? null;
            if ($departmentId === null) {
                continue;
            }
            $defs = CurriculumSubjectCatalogReference::subjectsFor($pair['branch'], $pair['department']);
            $required = [];
            $electives = [];
            foreach ($defs as $def) {
                $id = $this->subjects[$def['name']] ?? null;
                if ($id === null) {
                    continue;
                }
                if ((int) $def['subject_type'] === 2) {
                    $electives[] = $id;
                } else {
                    $required[] = $id;
                }
            }
            if ($required === []) {
                continue;
            }

            foreach (AdmissionCatalogReference::CLASSES as $classDef) {
                $class = $this->classes[$classDef['code']] ?? null;
                if ($class === null) {
                    continue;
                }
                $result = $create->handle(new CreateCurriculumCommand(
                    schoolId: $this->schoolId,
                    academicYearId: $this->yearId,
                    gradeLevelId: $class['grade_level_id'],
                    name: $pair['branch'].' — '.$pair['department'].' — '.$classDef['name'],
                    specializationId: null,
                    idempotencyKey: 'scn-cur-'.$departmentId.'-'.$classDef['code'],
                    subjectIds: $required,
                    departmentId: $departmentId,
                ));
                if ($result->failed()) {
                    throw new \RuntimeException('Curriculum create failed: '.implode(',', $result->errors));
                }
                foreach ($electives as $order => $subjectId) {
                    $link->handle(new LinkCurriculumSubjectCommand(
                        schoolId: $this->schoolId,
                        curriculumId: (int) $result->curriculumId,
                        subjectId: $subjectId,
                        weeklyHours: 2,
                        isRequired: false,
                        subjectOrder: 100 + $order,
                        idempotencyKey: 'scn-cur-el-'.$result->curriculumId.'-'.$subjectId,
                    ));
                }
                $count++;
            }
        }

        $this->summary['curricula'] = $count;
    }

    /** Networks practical needs microprocessors: blocked until a passing grade exists. */
    private function createPrerequisites(): void
    {
        $subject = $this->subjects['شبكات الحاسوب'] ?? null;
        $prerequisite = $this->subjects['المعالجات الدقيقة'] ?? null;
        if ($subject !== null && $prerequisite !== null) {
            app(AddSubjectPrerequisiteHandler::class)->handle(new AddSubjectPrerequisiteCommand(
                subjectId: $subject,
                prerequisiteSubjectId: $prerequisite,
                idempotencyKey: 'scn-prereq-networks',
            ));
            $this->summary['prerequisites'] = 1;
        }
    }

    // ------------------------------------------------------- admission flow

    private function runAdmissionsAndEnrollments(): void
    {
        $plan = [];
        foreach (self::ADMISSION_ONLY as $outcome => $n) {
            $plan = array_merge($plan, array_fill(0, $n, $outcome));
        }
        foreach (self::STUDENT_OUTCOMES as $outcome => $n) {
            $plan = array_merge($plan, array_fill(0, $n, $outcome));
        }

        $placements = $this->placementCycle();
        $capacityFill = 0;
        /** @var array<string, list<int>> $studentsByOutcome */
        $studentsByOutcome = [];
        /** @var array<int, array{class:string, section:string, branch:int, department:int}> $placementByStudent */
        $placementByStudent = [];

        foreach ($plan as $index => $outcome) {
            $seq = $index + 1;
            $applicant = $this->applicant($seq);
            $placement = $placements[$index % count($placements)];
            if ($outcome === 'enrolled' && $capacityFill < self::CAPACITY_SEATS) {
                // The first enrolled students fill the 2-seat demo section.
                $placement['class'] = self::CAPACITY_CLASS;
                $placement['section'] = self::CAPACITY_SECTION;
                $capacityFill++;
            }
            if ($outcome === 'capacity_blocked') {
                $placement['class'] = self::CAPACITY_CLASS;
                $placement['section'] = self::CAPACITY_SECTION;
            }

            $studentId = $outcome === 'registered_direct'
                ? $this->registerDirect($seq, $applicant, $placement)
                : $this->applyAndProgress($seq, $applicant, $placement, $outcome);

            $this->summary['applications'] = ($this->summary['applications'] ?? 0) + 1;
            if ($studentId !== null) {
                $studentsByOutcome[$outcome][] = $studentId;
                $placementByStudent[$studentId] = $placement;
            }
        }

        $this->enrollGroups($studentsByOutcome, $placementByStudent);
        $this->changeStudentStatuses($studentsByOutcome);

        // Outbox → audit + automatic curriculum subjects for every new enrollment.
        $outbox = app(EloquentOutboxRepository::class);
        for ($run = 0; $run < 500 && $outbox->fetchUnprocessed(1) !== []; $run++) {
            (new ProcessOutboxJob)->handle($outbox);
        }
        $this->summary['enrollment_subjects'] = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))
            ->where('is_elective', false)->count();

        $this->assignElectives();
        $this->changeEnrollmentStatuses($studentsByOutcome);
        $this->summary['students'] = array_sum(array_map('count', $studentsByOutcome));
    }

    /**
     * @return list<array{class:string, section:string, branch:int, department:int, branch_name:string, department_name:string, class_name:string}>
     */
    private function placementCycle(): array
    {
        $cycle = [];
        $pairs = AdmissionCatalogReference::placementPairs();
        foreach ($pairs as $p => $pair) {
            $departmentId = $this->departments[$pair['branch'].'|'.$pair['department']] ?? null;
            // Branch comes from the department row, never from the (possibly duplicated) name.
            $branchId = $departmentId !== null ? ($this->departmentBranch[$departmentId] ?? null) : null;
            if ($branchId === null || $departmentId === null) {
                continue;
            }
            $classDef = AdmissionCatalogReference::CLASSES[$p % count(AdmissionCatalogReference::CLASSES)];
            // Regular placements use sections A/B (C of CLS-3 is the capacity demo).
            $section = $classDef['code'] === self::CAPACITY_CLASS
                ? (['SEC-A', 'SEC-B'][$p % 2])
                : (['SEC-A', 'SEC-B', 'SEC-C'][$p % 3]);
            $cycle[] = [
                'class' => $classDef['code'],
                'class_name' => $classDef['name'],
                'section' => $section,
                'branch' => $branchId,
                'department' => $departmentId,
                'branch_name' => $pair['branch'],
                'department_name' => $pair['department'],
            ];
        }

        return $cycle;
    }

    /**
     * @param  array<string, mixed>  $applicant
     * @param  array<string, mixed>  $placement
     */
    private function applyAndProgress(int $seq, array $applicant, array $placement, string $outcome): ?int
    {
        $isTransfer = $seq % 5 === 0 || $seq % 5 === 2;
        $class = $this->classes[$placement['class']];
        $result = app(CreateApplicationDraftHandler::class)->handle(new CreateApplicationDraftCommand(
            schoolId: $this->schoolId,
            applicationPeriodId: $this->periodId,
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
            targetSchoolId: $this->schoolId,
            intendedGradeName: $placement['class_name'],
            requestKind: $isTransfer ? 1 : 2,
            nationalId: $applicant['national_id'],
            gradeLevelId: $class['grade_level_id'],
            branchId: $placement['branch'],
            branchName: $placement['branch_name'],
            departmentName: $placement['department_name'],
            governorate: $applicant['governorate'],
            administrativeUnit: $applicant['administrative_unit'],
            neighborhood: $applicant['neighborhood'],
            fatherOccupation: $applicant['father_occupation'],
            motherOccupation: $applicant['mother_occupation'],
            studentMobile: $seq % 9 === 0 ? null : $applicant['student_mobile'],
            guardianMobile: $applicant['guardian_mobile'],
            previousSchoolName: $isTransfer ? $applicant['previous_school_name'] : null,
            graduationYear: $isTransfer ? 2025 : null,
            previousGpa: $isTransfer ? $applicant['previous_gpa'] : null,
            mathematicsGrade: $isTransfer ? $applicant['mathematics_grade'] : null,
            physicsGrade: $isTransfer ? $applicant['physics_grade'] : null,
            previousStudyTrack: $isTransfer ? (($seq % 2) + 1) : null,
            notes: $this->notesFor($outcome),
            idempotencyKey: 'scn-create-'.$seq,
        ));
        $applicationId = (int) $result->applicationId;
        $this->attachDocuments($seq, $applicationId);

        foreach ($this->pathFor($outcome) as $step => $status) {
            app(TransitionApplicationStatusHandler::class)->handle(new TransitionApplicationStatusCommand(
                schoolId: $this->schoolId,
                applicationId: $applicationId,
                toStatus: $status->value,
                reviewedBy: null,
                notes: in_array($status, [ApplicationStatus::Rejected, ApplicationStatus::Withdrawn], true)
                    ? $this->notesFor($outcome)
                    : null,
                idempotencyKey: 'scn-tr-'.$seq.'-'.$step,
            ));
        }

        $studentId = DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $applicationId)->value('student_id');

        return $studentId !== null ? (int) $studentId : null;
    }

    /** @return list<ApplicationStatus> */
    private function pathFor(string $outcome): array
    {
        $toReview = [ApplicationStatus::Submitted, ApplicationStatus::UnderReview];

        return match ($outcome) {
            'draft' => [],
            'submitted' => [ApplicationStatus::Submitted],
            'under_review' => $toReview,
            'interview' => [...$toReview, ApplicationStatus::Interview],
            'waitlisted' => [...$toReview, ApplicationStatus::Interview, ApplicationStatus::Waitlisted],
            'rejected' => [...$toReview, ApplicationStatus::Interview, ApplicationStatus::Rejected],
            'withdrawn' => [ApplicationStatus::Submitted, ApplicationStatus::Withdrawn],
            // Every student outcome is accepted (acceptance converts to a student).
            default => [...$toReview, ApplicationStatus::Interview, ApplicationStatus::Accepted],
        };
    }

    private function notesFor(string $outcome): ?string
    {
        return match ($outcome) {
            'rejected' => 'لم يستوفِ معدل القبول للاختصاص المطلوب',
            'withdrawn' => 'انسحب ولي الأمر قبل المقابلة',
            'waitlisted' => 'قائمة انتظار — الاختصاص مكتمل العدد',
            'interview' => 'موعد المقابلة الأسبوع القادم',
            'awaiting_enrollment' => 'مقبول — بانتظار التوزيع على الصف والشعبة',
            'capacity_blocked' => 'مقبول — الشعبة المطلوبة ممتلئة',
            'suspended' => 'موقوف بقرار إداري',
            'withdrawn_student' => 'انسحب بعد القبول',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $applicant
     * @param  array<string, mixed>  $placement
     */
    private function registerDirect(int $seq, array $applicant, array $placement): int
    {
        $class = $this->classes[$placement['class']];
        $result = app(RegisterStudentViaAdmissionHandler::class)->handle(new RegisterStudentViaAdmissionCommand(
            schoolId: $this->schoolId,
            applicationPeriodId: $this->periodId,
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
            targetSchoolId: $this->schoolId,
            intendedGradeName: $placement['class_name'],
            requestKind: 2,
            nationalId: $applicant['national_id'],
            gradeLevelId: $class['grade_level_id'],
            branchId: $placement['branch'],
            branchName: $placement['branch_name'],
            departmentName: $placement['department_name'],
            governorate: $applicant['governorate'],
            administrativeUnit: $applicant['administrative_unit'],
            neighborhood: $applicant['neighborhood'],
            fatherOccupation: $applicant['father_occupation'],
            motherOccupation: $applicant['mother_occupation'],
            studentMobile: $applicant['student_mobile'],
            guardianMobile: $applicant['guardian_mobile'],
            notes: 'تسجيل مباشر من صفحة القبول',
            idempotencyKey: 'scn-direct-'.$seq,
        ));

        return (int) $result->studentId;
    }

    private function attachDocuments(int $seq, int $applicationId): void
    {
        // Complete files for most, partial for some, none for a few (profile gaps).
        $count = [5, 5, 5, 2, 5, 0, 5][$seq % 7];
        foreach (array_slice(self::DOCUMENT_TYPES, 0, $count) as $docType) {
            $fileName = sprintf('scn-%03d-doc-%d.png', $seq, $docType);
            $storageKey = sprintf('admission/%d/%s', $applicationId, $fileName);
            Storage::disk('local')->put($storageKey, $this->docBytes);
            app(RegisterApplicationDocumentHandler::class)->handle(new RegisterApplicationDocumentCommand(
                schoolId: $this->schoolId,
                applicationId: $applicationId,
                documentType: $docType,
                fileName: $fileName,
                storageKey: $storageKey,
                fileHash: hash('sha256', $this->docBytes),
                idempotencyKey: 'scn-doc-'.$seq.'-'.$docType,
            ));
        }
    }

    // ------------------------------------------------------------ enrollment

    /**
     * @param  array<string, list<int>>  $byOutcome
     * @param  array<int, array<string, mixed>>  $placementByStudent
     */
    private function enrollGroups(array $byOutcome, array $placementByStudent): void
    {
        $toEnroll = array_merge(
            $byOutcome['enrolled'] ?? [],
            $byOutcome['enrolled_cancelled'] ?? [],
            $byOutcome['enrolled_suspended'] ?? [],
            $byOutcome['enrolled_withdrawn'] ?? [],
            $byOutcome['registered_direct'] ?? [],
            $byOutcome['capacity_blocked'] ?? [],
        );

        // Group by identical placement → one bulk command per group (as the UI does).
        $groups = [];
        foreach ($toEnroll as $studentId) {
            $p = $placementByStudent[$studentId];
            $groups[$p['class'].'|'.$p['section'].'|'.$p['branch'].'|'.$p['department']][] = $studentId;
        }

        $enrolled = 0;
        $skipped = 0;
        foreach ($groups as $key => $studentIds) {
            [$classCode, $sectionCode, $branchId, $departmentId] = explode('|', $key);
            $class = $this->classes[$classCode];
            $result = app(BulkEnrollStudentsHandler::class)->handle(new BulkEnrollStudentsCommand(
                schoolId: $this->schoolId,
                academicYearId: $this->yearId,
                studentIds: $studentIds,
                classId: $class['id'],
                sectionId: $class['sections'][$sectionCode],
                effectiveFrom: '2026-09-01',
                branchId: (int) $branchId,
                departmentId: (int) $departmentId,
                idempotencyKey: 'scn-enroll-'.md5($key),
            ));
            $enrolled += count($result->enrolledStudentIds);
            $skipped += count($result->skipped);
        }

        $this->summary['enrolled'] = $enrolled;
        $this->summary['enroll_skipped'] = $skipped;
    }

    /** @param  array<string, list<int>>  $byOutcome */
    private function changeStudentStatuses(array $byOutcome): void
    {
        $handler = app(ChangeStudentStatusesHandler::class);
        if (($byOutcome['suspended'] ?? []) !== []) {
            $handler->handle(new ChangeStudentStatusesCommand($this->schoolId, $byOutcome['suspended'], 2, 'scn-st-suspend'));
        }
        if (($byOutcome['withdrawn_student'] ?? []) !== []) {
            $handler->handle(new ChangeStudentStatusesCommand($this->schoolId, $byOutcome['withdrawn_student'], 4, 'scn-st-withdraw'));
        }
    }

    /**
     * Cancelled → CancelEnrollment; suspended / withdrawn → the enrollments page status
     * action (student lifecycle status, mapped to the enrollment by policy).
     *
     * @param  array<string, list<int>>  $byOutcome
     */
    private function changeEnrollmentStatuses(array $byOutcome): void
    {
        foreach ($this->activeEnrollmentIds($byOutcome['enrolled_cancelled'] ?? []) as $enrollmentId) {
            app(CancelEnrollmentHandler::class)->handle(new CancelEnrollmentCommand(
                enrollmentId: $enrollmentId,
                schoolId: $this->schoolId,
                effectiveTo: '2026-11-15',
                idempotencyKey: 'scn-cancel-'.$enrollmentId,
            ));
        }

        $handler = app(ChangeEnrollmentStatusesHandler::class);
        foreach (['enrolled_suspended' => 2, 'enrolled_withdrawn' => 4] as $outcome => $status) {
            $ids = $this->activeEnrollmentIds($byOutcome[$outcome] ?? []);
            if ($ids !== []) {
                $handler->handle(new ChangeEnrollmentStatusesCommand(
                    schoolId: $this->schoolId,
                    enrollmentIds: $ids,
                    status: $status,
                    effectiveTo: '2026-11-15',
                    idempotencyKey: 'scn-enr-status-'.$outcome,
                ));
            }
        }
    }

    /** A few students also take an elective (optional curriculum subject). */
    private function assignElectives(): void
    {
        $rows = DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as cl', 'cl.id', '=', 'e.class_id')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', function ($join): void {
                $join->on('c.grade_level_id', '=', 'cl.grade_level_id')
                    ->on('c.department_id', '=', 'e.department_id')
                    ->on('c.academic_year_id', '=', 'e.academic_year_id');
            })
            ->join(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs', 'cs.curriculum_id', '=', 'c.id')
            ->where('e.school_id', $this->schoolId)
            ->where('e.status', 1)
            ->where('cs.is_required', false)
            ->orderBy('e.id')
            ->limit(6)
            ->get(['e.id as enrollment_id', 'cs.subject_id']);

        $assigned = 0;
        foreach ($rows as $row) {
            $result = app(AssignEnrollmentSubjectHandler::class)->handle(new AssignEnrollmentSubjectCommand(
                schoolId: $this->schoolId,
                enrollmentId: (int) $row->enrollment_id,
                subjectId: (int) $row->subject_id,
                isElective: true,
                idempotencyKey: 'scn-elective-'.$row->enrollment_id.'-'.$row->subject_id,
            ));
            $assigned += $result->success ? 1 : 0;
        }
        $this->summary['electives'] = $assigned;
    }

    /**
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    private function activeEnrollmentIds(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->whereIn('student_id', $studentIds)->where('academic_year_id', $this->yearId)->where('status', 1)
            ->pluck('id')->map(static fn ($id): int => (int) $id)->values()->all();
    }

    // --------------------------------------------------------------- people

    /** @return array<string, mixed> Realistic, unique Iraqi applicant data. */
    private function applicant(int $seq): array
    {
        $boys = ['أحمد', 'علي', 'حسن', 'حسين', 'محمد', 'يوسف', 'كرار', 'مصطفى', 'عباس', 'مرتضى', 'سجاد', 'حيدر', 'زين العابدين', 'منتظر', 'باقر'];
        $girls = ['فاطمة', 'زينب', 'مريم', 'نور الهدى', 'سارة', 'رقية', 'آية', 'رغد', 'دعاء', 'تبارك', 'بنين', 'هبة', 'أمل', 'شهد', 'زهراء'];
        $men = ['جواد', 'كاظم', 'جاسم', 'طالب', 'ناصر', 'ماجد', 'فؤاد', 'رياض', 'سعد', 'خليل', 'جبار', 'فاضل', 'عماد', 'قاسم', 'رزاق', 'عدنان', 'صباح', 'هادي'];
        $tribes = ['التميمي', 'الجبوري', 'العامري', 'الحسيني', 'الساعدي', 'الشمري', 'الدليمي', 'الزبيدي', 'الكعبي', 'الموسوي', 'الخفاجي', 'الربيعي', 'الطائي', 'العبيدي', 'الفتلاوي', 'المالكي', 'الكناني'];
        $places = ['بغداد', 'البصرة', 'النجف', 'كربلاء', 'بابل', 'واسط', 'ذي قار', 'ميسان', 'الديوانية', 'المثنى', 'ديالى', 'صلاح الدين'];
        $areas = ['الكرادة', 'المنصور', 'الأعظمية', 'الكاظمية', 'الشعلة', 'حي الجامعة', 'زيونة', 'البياع', 'الدورة', 'حي العامل', 'الغدير', 'بغداد الجديدة'];
        $jobs = ['موظف', 'معلم', 'كاسب', 'مهندس', 'عسكري', 'متقاعد', 'سائق', 'طبيب', 'نجار', 'محاسب'];

        $female = $seq % 2 === 0;
        $pick = static fn (array $list, int $k): string => $list[$k % count($list)];

        return [
            'first_name' => $pick($female ? $girls : $boys, intdiv($seq, 2)),
            'father_name' => $pick($men, $seq),
            'grandfather_name' => $pick($men, $seq * 3 + 1),
            'great_grandfather_name' => $pick($men, $seq * 5 + 2),
            'last_name' => $pick($tribes, $seq * 7),
            'mother_name' => $pick($girls, $seq * 2 + 3),
            'maternal_father_name' => $pick($men, $seq * 11 + 4),
            'maternal_grandfather_name' => $pick($men, $seq * 13 + 5),
            'national_id' => sprintf('2009%08d', 31000000 + $seq * 37),
            'birth_date' => sprintf('%04d-%02d-%02d', 2008 + ($seq % 3), ($seq % 12) + 1, ($seq % 28) + 1),
            'birth_place' => $pick($places, $seq),
            'gender' => $female ? 2 : 1,
            'governorate' => $pick($places, $seq + 1),
            'administrative_unit' => ($seq % 3) + 1,
            'neighborhood' => $pick($areas, $seq),
            'father_occupation' => $pick($jobs, $seq),
            'mother_occupation' => $seq % 3 === 0 ? 'معلمة' : 'ربة بيت',
            'student_mobile' => sprintf('077%08d', 10000000 + $seq * 4111),
            'guardian_mobile' => sprintf('078%08d', 20000000 + $seq * 3271),
            'previous_school_name' => 'متوسطة '.$pick($areas, $seq + 3).' للبنين والبنات',
            'previous_gpa' => sprintf('%.2f', 62 + ($seq * 7 % 35)),
            'mathematics_grade' => sprintf('%.2f', 55 + ($seq * 11 % 44)),
            'physics_grade' => sprintf('%.2f', 52 + ($seq * 13 % 46)),
        ];
    }

    private function tinyPng(): string
    {
        $path = storage_path('app/seed/wf-doc-sample.png');
        if (is_file($path)) {
            return (string) file_get_contents($path);
        }
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $image = imagecreatetruecolor(120, 120);
        imagefill($image, 0, 0, imagecolorallocate($image, 224, 225, 221));
        imagestring($image, 5, 30, 52, 'SIS DOC', imagecolorallocate($image, 27, 38, 59));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);
        file_put_contents($path, $png);

        return $png;
    }
}
