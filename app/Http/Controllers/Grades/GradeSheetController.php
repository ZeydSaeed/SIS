<?php

namespace App\Http\Controllers\Grades;

use App\Application\Exams\Commands\EnterSessionGradesCommand;
use App\Application\Exams\Commands\EnterSessionGradesHandler;
use App\Application\Exams\DTOs\SessionGradeRowDTO;
use App\Application\Exams\Queries\GetGradeEntrySheetHandler;
use App\Application\Exams\Queries\GetGradeEntrySheetQuery;
use App\Application\Exams\Support\SessionGradeRowGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\EnterSessionGradesRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «إدخال درجات الجلسة» — roster of the seats the user may grade, entered through the official write path. */
final class GradeSheetController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function show(Request $request, GetGradeEntrySheetHandler $handler): Response
    {
        $this->authorize('create', StudentGradeRecord::class);

        $user = $request->user();
        assert($user !== null);
        $academicYearId = $this->academicYears->resolve($request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null);
        $sessionId = $request->filled('session_id') ? (int) $request->query('session_id') : null;
        $schoolWide = $user->can('enterAnySeat', StudentGradeRecord::class);

        $sheet = $academicYearId === null
            ? ['sessions' => [], 'session' => null, 'rows' => [], 'session_missing' => false, 'session_forbidden' => false]
            : $handler->handle(new GetGradeEntrySheetQuery(
                schoolId: $this->schoolContext->requireId(),
                academicYearId: $academicYearId,
                examSessionId: $sessionId,
                actorUserId: (int) $user->id,
                schoolWide: $schoolWide,
            ));

        abort_if($sheet['session_missing'], 404);
        if ($sheet['session_forbidden']) {
            $this->securityAudit->record(SecurityEventType::IdorBlocked, 'grades.sheet.view', 'denied', $user, "exam_session:{$sessionId}");
            abort(403);
        }

        $this->securityAudit->record(SecurityEventType::GradeDataAccess, 'grades.web.sheet', 'allowed', $user,
            $sessionId !== null ? "exam_session:{$sessionId}" : 'student_grades',
            ['academic_year_id' => $academicYearId, 'rows' => count($sheet['rows']), 'school_wide' => $schoolWide]);

        return Inertia::render('grades/sheet', [
            'sessions' => $sheet['sessions'],
            'session' => $sheet['session'],
            'rows' => $sheet['rows'],
            'outcomes' => $request->session()->get('grade_sheet_outcomes'),
            'scope' => ['school_wide' => $schoolWide],
            'filters' => ['academic_year_id' => $academicYearId, 'session_id' => $sessionId],
        ]);
    }

    public function store(EnterSessionGradesRequest $request, int $session, EnterSessionGradesHandler $handler): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $result = $handler->handle(new EnterSessionGradesCommand(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $session,
            actorUserId: (int) $user->id,
            schoolWide: $user->can('enterAnySeat', StudentGradeRecord::class),
            rows: array_map(static fn (array $row): SessionGradeRowDTO => new SessionGradeRowDTO(
                examEnrollmentId: (int) $row['exam_enrollment_id'],
                score: isset($row['score']) ? (string) $row['score'] : null,
                isAbsent: (bool) $row['is_absent'],
            ), $request->validated('rows')),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        foreach ($result->outcomes as $outcome) {
            if ($outcome['status'] === SessionGradeRowGate::FORBIDDEN) {
                $this->securityAudit->record(SecurityEventType::IdorBlocked, 'grades.enter.scope', 'denied', $user, "exam_enrollment:{$outcome['exam_enrollment_id']}");
            }
        }
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'grades.web.sheet.store', $result->success ? 'created' : 'partial', $user,
            "exam_session:{$session}", [
                'saved' => $result->saved,
                'failed' => $result->failed,
                'grade_ids' => array_values(array_filter(array_column($result->outcomes, 'grade_id'))),
            ]);

        return redirect()
            ->back()
            ->with('grade_sheet_outcomes', $result->outcomes)
            ->with($result->success ? 'success' : 'error', $result->success ? 'flash.grades.sheetSaved' : 'flash.grades.sheetPartial');
    }
}
