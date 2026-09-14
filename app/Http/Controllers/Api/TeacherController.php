<?php

namespace App\Http\Controllers\Api;

use App\Application\Teachers\Commands\AddTeacherQualificationCommand;
use App\Application\Teachers\Commands\AddTeacherQualificationHandler;
use App\Application\Teachers\Commands\AssignTeacherSchoolCommand;
use App\Application\Teachers\Commands\AssignTeacherSchoolHandler;
use App\Application\Teachers\Commands\AssignTeacherSubjectCommand;
use App\Application\Teachers\Commands\AssignTeacherSubjectHandler;
use App\Application\Teachers\Commands\AttachTeacherQualificationDocumentCommand;
use App\Application\Teachers\Commands\AttachTeacherQualificationDocumentHandler;
use App\Application\Teachers\Commands\ChangeTeacherEmployeeCodeCommand;
use App\Application\Teachers\Commands\ChangeTeacherEmployeeCodeHandler;
use App\Application\Teachers\Commands\DeactivateTeacherCommand;
use App\Application\Teachers\Commands\DeactivateTeacherHandler;
use App\Application\Teachers\Commands\ReactivateTeacherCommand;
use App\Application\Teachers\Commands\ReactivateTeacherHandler;
use App\Application\Teachers\Commands\LeaveTeacherSchoolCommand;
use App\Application\Teachers\Commands\LeaveTeacherSchoolHandler;
use App\Application\Teachers\Commands\RegisterTeacherCommand;
use App\Application\Teachers\Commands\RegisterTeacherHandler;
use App\Application\Teachers\Commands\SetTeacherPrimarySchoolCommand;
use App\Application\Teachers\Commands\SetTeacherPrimarySchoolHandler;
use App\Application\Teachers\Commands\UnlinkTeacherSubjectCommand;
use App\Application\Teachers\Commands\UnlinkTeacherSubjectHandler;
use App\Application\Teachers\Commands\UpdateTeacherCommand;
use App\Application\Teachers\Commands\UpdateTeacherHandler;
use App\Application\Teachers\Commands\VoidTeacherQualificationCommand;
use App\Application\Teachers\Commands\VoidTeacherQualificationHandler;
use App\Application\Teachers\Commands\RestoreTeacherQualificationCommand;
use App\Application\Teachers\Commands\RestoreTeacherQualificationHandler;
use App\Application\Teachers\DTOs\TeacherDTO;
use App\Application\Teachers\DTOs\TeacherQualificationDTO;
use App\Application\Teachers\DTOs\TeacherSchoolMembershipDTO;
use App\Application\Teachers\DTOs\TeacherSubjectDTO;
use App\Application\Teachers\Queries\GetTeacherHandler;
use App\Application\Teachers\Queries\GetTeacherQualificationHandler;
use App\Application\Teachers\Queries\GetTeacherQualificationQuery;
use App\Application\Teachers\Queries\GetTeacherQuery;
use App\Application\Teachers\Queries\GetTeacherSubjectHandler;
use App\Application\Teachers\Queries\GetTeacherSubjectQuery;
use App\Application\Teachers\Queries\ListTeacherSchoolsHandler;
use App\Application\Teachers\Queries\ListTeacherSchoolsQuery;
use App\Application\Teachers\Queries\ListTeacherSubjectsHandler;
use App\Application\Teachers\Queries\ListTeacherSubjectsQuery;
use App\Application\Teachers\Queries\ListTeacherQualificationsHandler;
use App\Application\Teachers\Queries\ListTeacherQualificationsQuery;
use App\Application\Teachers\Queries\ListTeachersHandler;
use App\Application\Teachers\Queries\ListTeachersQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\AddTeacherQualificationRequest;
use App\Http\Requests\Teachers\AssignTeacherSchoolRequest;
use App\Http\Requests\Teachers\AssignTeacherSubjectRequest;
use App\Http\Requests\Teachers\AttachTeacherQualificationDocumentRequest;
use App\Http\Requests\Teachers\ChangeTeacherEmployeeCodeRequest;
use App\Http\Requests\Teachers\DeactivateTeacherRequest;
use App\Http\Requests\Teachers\ReactivateTeacherRequest;
use App\Http\Requests\Teachers\LeaveTeacherSchoolRequest;
use App\Http\Requests\Teachers\ListTeacherQualificationsRequest;
use App\Http\Requests\Teachers\ListTeacherSchoolsRequest;
use App\Http\Requests\Teachers\ListTeacherSubjectsRequest;
use App\Http\Requests\Teachers\ShowTeacherQualificationRequest;
use App\Http\Requests\Teachers\ShowTeacherSubjectRequest;
use App\Http\Requests\Teachers\ListTeachersRequest;
use App\Http\Requests\Teachers\RegisterTeacherRequest;
use App\Http\Requests\Teachers\SetTeacherPrimarySchoolRequest;
use App\Http\Requests\Teachers\ShowTeacherRequest;
use App\Http\Requests\Teachers\UnlinkTeacherSubjectRequest;
use App\Http\Requests\Teachers\UpdateTeacherRequest;
use App\Http\Requests\Teachers\VoidTeacherQualificationRequest;
use App\Http\Requests\Teachers\RestoreTeacherQualificationRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Domain\Teachers\ValueObjects\TeacherStatus;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(
        RegisterTeacherRequest $request,
        RegisterTeacherHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RegisterTeacherCommand(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            employeeCode: (string) $request->validated('employee_code'),
            firstName: (string) $request->validated('first_name'),
            lastName: (string) $request->validated('last_name'),
            nationalId: $request->validated('national_id'),
            specializationField: $request->validated('specialization_field'),
            hireDate: $request->validated('hire_date'),
            userId: isset($request->validated()['user_id']) ? (int) $request->validated('user_id') : null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Teacher registration rejected.',
                'error_code' => $result->errors[0] ?? 'teachers.register_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.register',
            'created',
            $request->user(),
            'teacher:'.$result->teacherId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function index(
        ListTeachersRequest $request,
        ListTeachersHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $page = $handler->handle(new ListTeachersQuery(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            page: (int) ($request->validated('page') ?? 1),
            perPage: (int) ($request->validated('per_page') ?? 50),
        ));

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.list',
            'viewed',
            $request->user(),
            'school:'.$schoolId,
            ['total' => $page->total],
        );

        return response()->json([
            'data' => array_map([$this, 'payload'], $page->items),
            'meta' => [
                'correlation_id' => CorrelationContext::id(),
                'total' => $page->total,
                'page' => $page->page,
                'per_page' => $page->perPage,
            ],
        ]);
    }

    public function show(
        int $teacher,
        ShowTeacherRequest $request,
        GetTeacherHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetTeacherQuery(
            schoolId: $schoolId,
            teacherId: $teacher,
            academicYearId: isset($request->validated()['academic_year_id'])
                ? (int) $request->validated('academic_year_id')
                : null,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Teacher not found in school context.',
                'error_code' => 'teachers.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.show',
            'viewed',
            $request->user(),
            'teacher:'.$dto->id,
            [],
        );

        return response()->json([
            'data' => $this->payload($dto),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function update(
        int $teacher,
        UpdateTeacherRequest $request,
        UpdateTeacherHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new UpdateTeacherCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            firstName: (string) $request->validated('first_name'),
            lastName: (string) $request->validated('last_name'),
            nationalId: $request->validated('national_id'),
            specializationField: $request->validated('specialization_field'),
            hireDate: $request->validated('hire_date'),
            userId: array_key_exists('user_id', $request->validated())
                ? ($request->validated('user_id') !== null ? (int) $request->validated('user_id') : null)
                : null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.update_failed';

            return response()->json([
                'message' => 'Teacher update rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'teachers.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.update',
            'updated',
            $request->user(),
            'teacher:'.$teacher,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivate(
        int $teacher,
        DeactivateTeacherRequest $request,
        DeactivateTeacherHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new DeactivateTeacherCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.deactivate_failed';

            return response()->json([
                'message' => 'Teacher deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'teachers.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.deactivate',
            'deactivated',
            $request->user(),
            'teacher:'.$teacher,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'status' => TeacherStatus::Inactive,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivate(
        int $teacher,
        ReactivateTeacherRequest $request,
        ReactivateTeacherHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new ReactivateTeacherCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.reactivate_failed';

            return response()->json([
                'message' => 'Teacher reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'teachers.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.reactivate',
            'reactivated',
            $request->user(),
            'teacher:'.$teacher,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'status' => TeacherStatus::Active,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function indexSubjects(
        int $teacher,
        ListTeacherSubjectsRequest $request,
        ListTeacherSubjectsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListTeacherSubjectsQuery(
            schoolId: $schoolId,
            teacherId: $teacher,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Teacher not found in school context.',
                'error_code' => 'teachers.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.subject.list',
            'listed',
            $request->user(),
            'teacher:'.$teacher,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (TeacherSubjectDTO $dto): array => [
                'id' => $dto->id,
                'teacher_id' => $dto->teacherId,
                'subject_id' => $dto->subjectId,
                'academic_year_id' => $dto->academicYearId,
                'school_id' => $dto->schoolId,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showSubject(
        int $teacher,
        int $assignment,
        ShowTeacherSubjectRequest $request,
        GetTeacherSubjectHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetTeacherSubjectQuery(
            schoolId: $schoolId,
            teacherId: $teacher,
            assignmentId: $assignment,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Teacher subject assignment not found.',
                'error_code' => 'teachers.subject_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.subject.show',
            'viewed',
            $request->user(),
            'teacher_subject:'.$assignment,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'teacher_id' => $dto->teacherId,
                'subject_id' => $dto->subjectId,
                'academic_year_id' => $dto->academicYearId,
                'school_id' => $dto->schoolId,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function indexSchools(
        int $teacher,
        ListTeacherSchoolsRequest $request,
        ListTeacherSchoolsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $year = $request->validated('academic_year_id');
        $items = $handler->handle(new ListTeacherSchoolsQuery(
            schoolId: $schoolId,
            teacherId: $teacher,
            academicYearId: $year !== null ? (int) $year : null,
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Teacher not found in school context.',
                'error_code' => 'teachers.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.schools.list',
            'listed',
            $request->user(),
            'teacher:'.$teacher,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(static fn (TeacherSchoolMembershipDTO $dto): array => [
                'id' => $dto->id,
                'teacher_id' => $dto->teacherId,
                'school_id' => $dto->schoolId,
                'academic_year_id' => $dto->academicYearId,
                'is_primary' => $dto->isPrimary,
                'left_at' => $dto->leftAt,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function assignSubject(
        int $teacher,
        AssignTeacherSubjectRequest $request,
        AssignTeacherSubjectHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new AssignTeacherSubjectCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            subjectId: (int) $request->validated('subject_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Teacher subject assign rejected.',
                'error_code' => $result->errors[0] ?? 'teachers.subject_assign_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.subject.assign',
            'assigned',
            $request->user(),
            'teacher:'.$teacher,
            [
                'assignment_id' => $result->assignmentId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'assignment_id' => $result->assignmentId,
                'teacher_id' => $teacher,
                'subject_id' => (int) $request->validated('subject_id'),
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function unlinkSubject(
        int $teacher,
        UnlinkTeacherSubjectRequest $request,
        UnlinkTeacherSubjectHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new UnlinkTeacherSubjectCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            subjectId: (int) $request->validated('subject_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Teacher subject unlink rejected.',
                'error_code' => $result->errors[0] ?? 'teachers.subject_unlink_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.subject.unlink',
            $result->wasPresent ? 'unlinked' : 'already_absent',
            $request->user(),
            'teacher:'.$teacher,
            [
                'subject_id' => (int) $request->validated('subject_id'),
                'was_present' => $result->wasPresent,
            ],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $teacher,
                'subject_id' => (int) $request->validated('subject_id'),
                'was_present' => $result->wasPresent,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeQualification(
        int $teacher,
        AddTeacherQualificationRequest $request,
        AddTeacherQualificationHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new AddTeacherQualificationCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            academicYearId: (int) $request->validated('academic_year_id'),
            qualificationType: (int) $request->validated('qualification_type'),
            title: (string) $request->validated('title'),
            institution: $request->validated('institution'),
            yearObtained: $request->validated('year_obtained') !== null
                ? (int) $request->validated('year_obtained')
                : null,
            documentStorageKey: $request->validated('document_storage_key'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.qualification_add_failed';

            return response()->json([
                'message' => 'Teacher qualification add rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.qualification.add',
            'added',
            $request->user(),
            'teacher:'.$teacher,
            [
                'qualification_id' => $result->qualificationId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'qualification_id' => $result->qualificationId,
                'teacher_id' => $teacher,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexQualifications(
        int $teacher,
        ListTeacherQualificationsRequest $request,
        ListTeacherQualificationsHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListTeacherQualificationsQuery(
            schoolId: $schoolId,
            teacherId: $teacher,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Teacher not found in school context.',
                'error_code' => 'teachers.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.qualification.list',
            'listed',
            $request->user(),
            'teacher:'.$teacher,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (TeacherQualificationDTO $dto): array => [
                'id' => $dto->id,
                'teacher_id' => $dto->teacherId,
                'qualification_type' => $dto->qualificationType,
                'title' => $dto->title,
                'institution' => $dto->institution,
                'year_obtained' => $dto->yearObtained,
                'document_storage_key' => $dto->documentStorageKey,
                'status' => $dto->status,
                'effective_from' => $dto->effectiveFrom,
                'effective_to' => $dto->effectiveTo,
                'created_at' => $dto->createdAt,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showQualification(
        int $teacher,
        int $qualification,
        ShowTeacherQualificationRequest $request,
        GetTeacherQualificationHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetTeacherQualificationQuery(
            schoolId: $schoolId,
            teacherId: $teacher,
            qualificationId: $qualification,
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Teacher qualification not found.',
                'error_code' => 'teachers.qualification_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.qualification.show',
            'viewed',
            $request->user(),
            'teacher:'.$teacher,
            ['qualification_id' => $qualification],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'teacher_id' => $dto->teacherId,
                'qualification_type' => $dto->qualificationType,
                'title' => $dto->title,
                'institution' => $dto->institution,
                'year_obtained' => $dto->yearObtained,
                'document_storage_key' => $dto->documentStorageKey,
                'status' => $dto->status,
                'effective_from' => $dto->effectiveFrom,
                'effective_to' => $dto->effectiveTo,
                'created_at' => $dto->createdAt,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function voidQualification(
        int $teacher,
        int $qualification,
        VoidTeacherQualificationRequest $request,
        VoidTeacherQualificationHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new VoidTeacherQualificationCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            qualificationId: $qualification,
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Teacher qualification void rejected.',
                'error_code' => $result->errors[0] ?? 'teachers.qualification_void_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.qualification.void',
            'voided',
            $request->user(),
            'teacher:'.$teacher,
            [
                'qualification_id' => $result->qualificationId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'qualification_id' => $result->qualificationId,
                'teacher_id' => $teacher,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], 200);
    }

    public function restoreQualification(
        int $teacher,
        int $qualification,
        RestoreTeacherQualificationRequest $request,
        RestoreTeacherQualificationHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RestoreTeacherQualificationCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            qualificationId: $qualification,
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Teacher qualification restore rejected.',
                'error_code' => $result->errors[0] ?? 'teachers.qualification_restore_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.qualification.restore',
            'restored',
            $request->user(),
            'teacher:'.$teacher,
            [
                'qualification_id' => $result->qualificationId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'qualification_id' => $result->qualificationId,
                'teacher_id' => $teacher,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], 200);
    }

    public function attachQualificationDocument(
        int $teacher,
        int $qualification,
        AttachTeacherQualificationDocumentRequest $request,
        AttachTeacherQualificationDocumentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new AttachTeacherQualificationDocumentCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            qualificationId: $qualification,
            documentId: (int) $request->validated('document_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.qualification_attach_failed';

            return response()->json([
                'message' => 'Teacher qualification document attach rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], in_array($code, [
                'teachers.qualification_not_found',
                'teachers.qualification_document_not_found',
                'teachers.not_in_school_year',
            ], true) ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.qualification.attach_document',
            'attached',
            $request->user(),
            'teacher:'.$teacher,
            [
                'qualification_id' => $result->qualificationId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'qualification_id' => $result->qualificationId,
                'teacher_id' => $teacher,
                'document_storage_key' => $result->storageKey,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function assignSchool(
        int $teacher,
        AssignTeacherSchoolRequest $request,
        AssignTeacherSchoolHandler $handler,
    ): JsonResponse {
        $targetSchoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new AssignTeacherSchoolCommand(
            targetSchoolId: $targetSchoolId,
            sourceSchoolId: (int) $request->validated('source_school_id'),
            teacherId: $teacher,
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.assign_school_failed';

            return response()->json([
                'message' => 'Teacher school assign rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], in_array($code, ['teachers.not_in_source_school'], true) ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.assign_school',
            'assigned',
            $request->user(),
            'teacher:'.$teacher,
            [
                'teacher_school_id' => $result->teacherSchoolId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'teacher_school_id' => $result->teacherSchoolId,
                'school_id' => $targetSchoolId,
                'is_primary' => false,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function changeEmployeeCode(
        int $teacher,
        ChangeTeacherEmployeeCodeRequest $request,
        ChangeTeacherEmployeeCodeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new ChangeTeacherEmployeeCodeCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            employeeCode: (string) $request->validated('employee_code'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.employee_code_change_failed';

            return response()->json([
                'message' => 'Teacher employee code change rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'teachers.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.change_employee_code',
            'changed',
            $request->user(),
            'teacher:'.$teacher,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'employee_code' => $result->employeeCode,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function setPrimarySchool(
        int $teacher,
        SetTeacherPrimarySchoolRequest $request,
        SetTeacherPrimarySchoolHandler $handler,
    ): JsonResponse {
        $targetSchoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new SetTeacherPrimarySchoolCommand(
            targetSchoolId: $targetSchoolId,
            sourceSchoolId: (int) $request->validated('source_school_id'),
            teacherId: $teacher,
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.set_primary_failed';

            return response()->json([
                'message' => 'Teacher primary school change rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], in_array($code, [
                'teachers.not_in_source_school_year',
                'teachers.not_in_target_school_year',
            ], true) ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.set_primary_school',
            'primary_changed',
            $request->user(),
            'teacher:'.$teacher,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'school_id' => $targetSchoolId,
                'is_primary' => true,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function leaveSchool(
        int $teacher,
        LeaveTeacherSchoolRequest $request,
        LeaveTeacherSchoolHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new LeaveTeacherSchoolCommand(
            schoolId: $schoolId,
            teacherId: $teacher,
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'teachers.leave_school_failed';

            return response()->json([
                'message' => 'Teacher leave school rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'teachers.not_in_school_year' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            'teachers.leave_school',
            'left',
            $request->user(),
            'teacher:'.$teacher,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'teacher_id' => $result->teacherId,
                'school_id' => $schoolId,
                'left' => true,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TeacherDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'user_id' => $dto->userId,
            'employee_code' => $dto->employeeCode,
            'national_id' => $dto->nationalId,
            'first_name' => $dto->firstName,
            'last_name' => $dto->lastName,
            'full_name' => $dto->fullName,
            'specialization_field' => $dto->specializationField,
            'hire_date' => $dto->hireDate,
            'status' => $dto->status,
            'school_id' => $dto->schoolId,
            'academic_year_id' => $dto->academicYearId,
            'is_primary' => $dto->isPrimary,
        ];
    }
}
