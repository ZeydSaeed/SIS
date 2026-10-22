<?php

namespace App\Http\Controllers\Exams;

use App\Application\Exams\Commands\SeatClassInExamSessionCommand;
use App\Application\Exams\Commands\SeatClassInExamSessionHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\SeatExamSessionRequest;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;

/** «إجلاس طلاب» — seats a class / section into an exam session through the single-seat command. */
final class ExamSeatingController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function store(SeatExamSessionRequest $request, int $exam, int $session, SeatClassInExamSessionHandler $handler): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);
        $sectionId = $request->validated('section_id');

        $result = $handler->handle(new SeatClassInExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examId: $exam,
            examSessionId: $session,
            classId: (int) $request->validated('class_id'),
            sectionId: $sectionId !== null ? (int) $sectionId : null,
            actorUserId: (int) $user->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'exams.web.seat_class', $result->success ? 'created' : 'partial', $user,
            "exam_session:{$session}", ['class_id' => (int) $request->validated('class_id'), 'section_id' => $sectionId, 'counts' => $result->counts]);

        return redirect()
            ->back()
            ->with('exam_seating_result', ['session_id' => $session, 'counts' => $result->counts])
            ->with($result->success ? 'success' : 'error', $result->success ? 'flash.exams.seated' : 'flash.exams.seatedPartial');
    }
}
