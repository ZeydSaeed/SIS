<?php

namespace App\Http\Controllers\Api;

use App\Application\Curriculum\Commands\AddSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\AddSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\CreateCurriculumCommand;
use App\Application\Curriculum\Commands\CreateCurriculumHandler;
use App\Application\Curriculum\Commands\CreateSubjectCommand;
use App\Application\Curriculum\Commands\CreateSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateCurriculumCommand;
use App\Application\Curriculum\Commands\DeactivateCurriculumHandler;
use App\Application\Curriculum\Commands\DeactivateCurriculumSubjectCommand;
use App\Application\Curriculum\Commands\DeactivateCurriculumSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectCommand;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectHandler;
use App\Application\Curriculum\Commands\ReactivateCurriculumCommand;
use App\Application\Curriculum\Commands\ReactivateCurriculumHandler;
use App\Application\Curriculum\Commands\ReactivateSubjectCommand;
use App\Application\Curriculum\Commands\ReactivateSubjectHandler;
use App\Application\Curriculum\Commands\UpdateCurriculumCommand;
use App\Application\Curriculum\Commands\UpdateCurriculumHandler;
use App\Application\Curriculum\Commands\UpdateSubjectCommand;
use App\Application\Curriculum\Commands\UpdateSubjectHandler;
use App\Application\Curriculum\DTOs\CurriculumDTO;
use App\Application\Curriculum\DTOs\CurriculumSubjectDTO;
use App\Application\Curriculum\DTOs\PrerequisiteDTO;
use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Application\Curriculum\Queries\ListCurriculaHandler;
use App\Application\Curriculum\Queries\ListCurriculaQuery;
use App\Application\Curriculum\Queries\ListCurriculumSubjectsHandler;
use App\Application\Curriculum\Queries\ListCurriculumSubjectsQuery;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesHandler;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesQuery;
use App\Application\Curriculum\Queries\ListSubjectsHandler;
use App\Application\Curriculum\Queries\ListSubjectsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\AddSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\CreateCurriculumRequest;
use App\Http\Requests\Curriculum\CreateSubjectRequest;
use App\Http\Requests\Curriculum\DeactivateCurriculumRequest;
use App\Http\Requests\Curriculum\DeactivateCurriculumSubjectRequest;
use App\Http\Requests\Curriculum\DeactivateSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\DeactivateSubjectRequest;
use App\Http\Requests\Curriculum\LinkCurriculumSubjectRequest;
use App\Http\Requests\Curriculum\ListCurriculaRequest;
use App\Http\Requests\Curriculum\ListCurriculumSubjectsRequest;
use App\Http\Requests\Curriculum\ListSubjectPrerequisitesRequest;
use App\Http\Requests\Curriculum\ListSubjectsRequest;
use App\Http\Requests\Curriculum\ReactivateCurriculumRequest;
use App\Http\Requests\Curriculum\ReactivateSubjectRequest;
use App\Http\Requests\Curriculum\UpdateCurriculumRequest;
use App\Http\Requests\Curriculum\UpdateSubjectRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class CurriculumController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function storeSubject(
        CreateSubjectRequest $request,
        CreateSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateSubjectCommand(
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            nameEn: $request->validated('name_en'),
            subjectType: (int) $request->validated('subject_type'),
            creditHours: $request->validated('credit_hours') !== null
                ? (int) $request->validated('credit_hours')
                : null,
            maxGrade: (int) ($request->validated('max_grade') ?? 100),
            passGrade: (int) ($request->validated('pass_grade') ?? 50),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Subject create rejected.',
                'error_code' => $result->errors[0] ?? 'curriculum.subject_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.subjects.store',
            'created',
            $request->user(),
            'subject:'.$result->subjectId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'subject_id' => $result->subjectId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexSubjects(
        ListSubjectsRequest $request,
        ListSubjectsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListSubjectsQuery);

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.subjects.index',
            'listed',
            $request->user(),
            'subjects',
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (SubjectDTO $dto): array => $this->subjectPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateSubject(
        int $subject,
        DeactivateSubjectRequest $request,
        DeactivateSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateSubjectCommand(
            subjectId: $subject,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.subject_deactivate_failed';

            return response()->json([
                'message' => 'Subject deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.subject_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.subjects.deactivate',
            'deactivated',
            $request->user(),
            'subject:'.$result->subjectId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'subject_id' => $result->subjectId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function updateSubject(
        int $subject,
        UpdateSubjectRequest $request,
        UpdateSubjectHandler $handler,
    ): JsonResponse {
        /** @var array{name?: string, name_en?: ?string, subject_type?: int, credit_hours?: ?int, max_grade?: int, pass_grade?: int} $fields */
        $fields = [];
        foreach (['name', 'name_en', 'subject_type', 'credit_hours', 'max_grade', 'pass_grade'] as $key) {
            if ($request->exists($key)) {
                $fields[$key] = $request->validated($key);
            }
        }

        $result = $handler->handle(new UpdateSubjectCommand(
            subjectId: $subject,
            fields: $fields,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.subject_update_failed';

            return response()->json([
                'message' => 'Subject update rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.subject_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.subjects.update',
            'updated',
            $request->user(),
            'subject:'.$result->subjectId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'subject_id' => $result->subjectId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivateSubject(
        int $subject,
        ReactivateSubjectRequest $request,
        ReactivateSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateSubjectCommand(
            subjectId: $subject,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.subject_reactivate_failed';

            return response()->json([
                'message' => 'Subject reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.subject_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.subjects.reactivate',
            'reactivated',
            $request->user(),
            'subject:'.$result->subjectId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'subject_id' => $result->subjectId,
                'status' => 1,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeCurriculum(
        CreateCurriculumRequest $request,
        CreateCurriculumHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreateCurriculumCommand(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            gradeLevelId: (int) $request->validated('grade_level_id'),
            name: (string) $request->validated('name'),
            specializationId: $request->validated('specialization_id') !== null
                ? (int) $request->validated('specialization_id')
                : null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return response()->json([
                'message' => 'Curriculum create rejected.',
                'error_code' => $result->errors[0] ?? 'curriculum.curriculum_create_failed',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.curricula.store',
            'created',
            $request->user(),
            'curriculum:'.$result->curriculumId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'curriculum_id' => $result->curriculumId,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexCurricula(
        ListCurriculaRequest $request,
        ListCurriculaHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListCurriculaQuery(
            $schoolId,
            (int) $request->validated('academic_year_id'),
        ));

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.curricula.index',
            'listed',
            $request->user(),
            'school:'.$schoolId,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (CurriculumDTO $dto): array => $this->curriculumPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateCurriculum(
        int $curriculum,
        DeactivateCurriculumRequest $request,
        DeactivateCurriculumHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateCurriculumCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.curriculum_deactivate_failed';

            return response()->json([
                'message' => 'Curriculum deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.curriculum_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.curricula.deactivate',
            'deactivated',
            $request->user(),
            'curriculum:'.$result->curriculumId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'curriculum_id' => $result->curriculumId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivateCurriculum(
        int $curriculum,
        ReactivateCurriculumRequest $request,
        ReactivateCurriculumHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateCurriculumCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.curriculum_reactivate_failed';

            return response()->json([
                'message' => 'Curriculum reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.curriculum_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.curricula.reactivate',
            'reactivated',
            $request->user(),
            'curriculum:'.$result->curriculumId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'curriculum_id' => $result->curriculumId,
                'status' => 1,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function updateCurriculum(
        int $curriculum,
        UpdateCurriculumRequest $request,
        UpdateCurriculumHandler $handler,
    ): JsonResponse {
        /** @var array{name?: string, specialization_id?: ?int} $fields */
        $fields = [];
        if ($request->exists('name')) {
            $fields['name'] = (string) $request->validated('name');
        }
        if ($request->exists('specialization_id')) {
            $fields['specialization_id'] = $request->validated('specialization_id') !== null
                ? (int) $request->validated('specialization_id')
                : null;
        }

        $result = $handler->handle(new UpdateCurriculumCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            fields: $fields,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.curriculum_update_failed';

            return response()->json([
                'message' => 'Curriculum update rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.curriculum_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.curricula.update',
            'updated',
            $request->user(),
            'curriculum:'.$result->curriculumId,
            [
                'from_idempotency' => $result->fromIdempotencyCache,
                'fields' => $result->fields,
            ],
        );

        return response()->json([
            'data' => [
                'curriculum_id' => $result->curriculumId,
                'fields' => $result->fields,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeCurriculumSubject(
        int $curriculum,
        LinkCurriculumSubjectRequest $request,
        LinkCurriculumSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new LinkCurriculumSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            subjectId: (int) $request->validated('subject_id'),
            weeklyHours: $request->validated('weekly_hours') !== null
                ? (int) $request->validated('weekly_hours')
                : null,
            isRequired: (bool) ($request->validated('is_required') ?? true),
            subjectOrder: (int) ($request->validated('subject_order') ?? 0),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.curriculum_subject_link_failed';

            return response()->json([
                'message' => 'Curriculum subject link rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], in_array($code, [
                'curriculum.curriculum_not_found',
                'curriculum.subject_not_found',
            ], true) ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.curriculum_subjects.store',
            'linked',
            $request->user(),
            'curriculum_subject:'.$result->linkId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'link_id' => $result->linkId,
                'curriculum_id' => $curriculum,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexCurriculumSubjects(
        int $curriculum,
        ListCurriculumSubjectsRequest $request,
        ListCurriculumSubjectsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListCurriculumSubjectsQuery(
            $this->schoolContext->requireId(),
            $curriculum,
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Curriculum not found.',
                'error_code' => 'curriculum.curriculum_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.curriculum_subjects.index',
            'listed',
            $request->user(),
            'curriculum:'.$curriculum,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (CurriculumSubjectDTO $dto): array => $this->curriculumSubjectPayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateCurriculumSubject(
        int $link,
        DeactivateCurriculumSubjectRequest $request,
        DeactivateCurriculumSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateCurriculumSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            linkId: $link,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.curriculum_subject_deactivate_failed';

            return response()->json([
                'message' => 'Curriculum subject deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.curriculum_subject_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.curriculum_subjects.deactivate',
            'deactivated',
            $request->user(),
            'curriculum_subject:'.$result->linkId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'link_id' => $result->linkId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storePrerequisite(
        int $subject,
        AddSubjectPrerequisiteRequest $request,
        AddSubjectPrerequisiteHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new AddSubjectPrerequisiteCommand(
            subjectId: $subject,
            prerequisiteSubjectId: (int) $request->validated('prerequisite_subject_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.prerequisite_add_failed';

            return response()->json([
                'message' => 'Subject prerequisite add rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], in_array($code, [
                'curriculum.subject_not_found',
                'curriculum.prerequisite_subject_not_found',
            ], true) ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.prerequisites.store',
            'added',
            $request->user(),
            'prerequisite:'.$result->prerequisiteId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'prerequisite_id' => $result->prerequisiteId,
                'subject_id' => $subject,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexPrerequisites(
        int $subject,
        ListSubjectPrerequisitesRequest $request,
        ListSubjectPrerequisitesHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListSubjectPrerequisitesQuery($subject));

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.prerequisites.index',
            'listed',
            $request->user(),
            'subject:'.$subject,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (PrerequisiteDTO $dto): array => $this->prerequisitePayload($dto), $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivatePrerequisite(
        int $prerequisite,
        DeactivateSubjectPrerequisiteRequest $request,
        DeactivateSubjectPrerequisiteHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateSubjectPrerequisiteCommand(
            prerequisiteId: $prerequisite,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'curriculum.prerequisite_deactivate_failed';

            return response()->json([
                'message' => 'Subject prerequisite deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'curriculum.prerequisite_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.prerequisites.deactivate',
            'deactivated',
            $request->user(),
            'prerequisite:'.$result->prerequisiteId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'prerequisite_id' => $result->prerequisiteId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function curriculumPayload(CurriculumDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'school_id' => $dto->schoolId,
            'academic_year_id' => $dto->academicYearId,
            'grade_level_id' => $dto->gradeLevelId,
            'specialization_id' => $dto->specializationId,
            'name' => $dto->name,
            'status' => $dto->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function curriculumSubjectPayload(CurriculumSubjectDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'curriculum_id' => $dto->curriculumId,
            'subject_id' => $dto->subjectId,
            'weekly_hours' => $dto->weeklyHours,
            'is_required' => $dto->isRequired,
            'subject_order' => $dto->subjectOrder,
            'status' => $dto->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subjectPayload(SubjectDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'code' => $dto->code,
            'name' => $dto->name,
            'name_en' => $dto->nameEn,
            'subject_type' => $dto->subjectType,
            'credit_hours' => $dto->creditHours,
            'max_grade' => $dto->maxGrade,
            'pass_grade' => $dto->passGrade,
            'status' => $dto->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prerequisitePayload(PrerequisiteDTO $dto): array
    {
        return [
            'id' => $dto->id,
            'subject_id' => $dto->subjectId,
            'prerequisite_subject_id' => $dto->prerequisiteSubjectId,
            'status' => $dto->status,
            'created_at' => $dto->createdAt,
        ];
    }
}
