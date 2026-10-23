<?php

namespace App\Http\Controllers\Exams;

use App\Application\Exams\Commands\SaveExamTypeCommand;
use App\Application\Exams\Commands\SaveExamTypeHandler;
use App\Application\Exams\Queries\ListExamTypesHandler;
use App\Application\Exams\Queries\ListExamTypesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\SaveExamTypeRequest;
use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «أنواع الامتحانات» — the school's exam types and weights (exam.view to read, exam.type.manage to change). */
final class ExamTypeController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, ListExamTypesHandler $handler): Response
    {
        $this->authorize('viewAny', ExamRecord::class);
        $user = $request->user();
        assert($user !== null);

        $types = $handler->handle(new ListExamTypesQuery($this->schoolContext->requireId()));
        $this->securityAudit->record(SecurityEventType::GradeDataAccess, 'exams.web.types.index', 'allowed', $user, 'exam_types', ['count' => count($types)]);

        return Inertia::render('exams/types', [
            'types' => ['data' => $types],
            'can_manage' => $user->can('manageTypes', ExamRecord::class),
        ]);
    }

    public function store(SaveExamTypeRequest $request, SaveExamTypeHandler $handler): RedirectResponse
    {
        return $this->save($request, null, $handler);
    }

    public function update(SaveExamTypeRequest $request, int $type, SaveExamTypeHandler $handler): RedirectResponse
    {
        return $this->save($request, $type, $handler);
    }

    private function save(SaveExamTypeRequest $request, ?int $type, SaveExamTypeHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new SaveExamTypeCommand(
            schoolId: $this->schoolContext->requireId(),
            examTypeId: $type,
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            weightPercentage: (int) $request->validated('weight_percentage'),
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'exams.web.types.save', $result->created ? 'created' : 'updated',
            $request->user(), "exam_type:{$result->examTypeId}", ['weight_percentage' => (int) $request->validated('weight_percentage')]);

        return redirect()->route('exams.types.index')->with('success', $result->created ? 'flash.exams.typeCreated' : 'flash.exams.typeUpdated');
    }
}
