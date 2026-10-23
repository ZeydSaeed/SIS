<?php

namespace App\Http\Controllers\Results;

use App\Application\Results\Commands\ProcessTermResultsBatchCommand;
use App\Application\Results\Commands\ProcessTermResultsBatchHandler;
use App\Application\Results\Queries\GetTermResultSheetHandler;
use App\Application\Results\Queries\GetTermResultSheetQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\CalculateTermResultsBatchRequest;
use App\Http\Requests\Results\FinalizeTermResultsBatchRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «احتساب نتائج الفصل» — term results of one subject for a class / section, calculated and finalized per student. */
final class TermResultSheetController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function show(Request $request, GetTermResultSheetHandler $handler): Response
    {
        $this->authorize('viewOfficial', TermResultRecord::class);
        $user = $request->user();
        assert($user !== null);
        $int = static fn (string $key): ?int => $request->filled($key) ? (int) $request->query($key) : null;
        $academicYearId = $this->academicYears->resolve($int('academic_year_id'));
        $filters = ['academic_year_id' => $academicYearId, 'term_id' => $int('term_id'), 'subject_id' => $int('subject_id'), 'class_id' => $int('class_id'), 'section_id' => $int('section_id')];

        $sheet = $academicYearId === null
            ? ['terms' => [], 'classes' => [], 'subjects' => [], 'rows' => []]
            : $handler->handle(new GetTermResultSheetQuery($this->schoolContext->requireId(), $academicYearId, $filters['term_id'], $filters['subject_id'], $filters['class_id'], $filters['section_id']));

        $this->securityAudit->record(SecurityEventType::ResultsDataAccess, 'results.web.sheet', 'allowed', $user, 'term_results', ['rows' => count($sheet['rows'])]);

        return Inertia::render('results/sheet', $sheet + [
            'filters' => $filters,
            'abilities' => ['calculate' => $user->can('calculate', TermResultRecord::class), 'finalize' => $user->can('finalize', TermResultRecord::class)],
            'outcomes' => $request->session()->get('term_results_outcome'),
        ]);
    }

    public function calculate(CalculateTermResultsBatchRequest $request, ProcessTermResultsBatchHandler $handler): RedirectResponse
    {
        return $this->process($request, $handler, ProcessTermResultsBatchCommand::CALCULATE);
    }

    public function finalize(FinalizeTermResultsBatchRequest $request, ProcessTermResultsBatchHandler $handler): RedirectResponse
    {
        return $this->process($request, $handler, ProcessTermResultsBatchCommand::FINALIZE);
    }

    private function process(FormRequest $request, ProcessTermResultsBatchHandler $handler, string $mode): RedirectResponse
    {
        $sectionId = $request->validated('section_id');
        $result = $handler->handle(new ProcessTermResultsBatchCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            subjectId: (int) $request->validated('subject_id'),
            classId: (int) $request->validated('class_id'),
            sectionId: $sectionId !== null ? (int) $sectionId : null,
            mode: $mode,
            actorUserId: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(SecurityEventType::ResultsDataModified, "results.web.term_batch.{$mode}", $result->success ? 'completed' : 'partial', $request->user(),
            'term_results', ['term_id' => (int) $request->validated('term_id'), 'subject_id' => (int) $request->validated('subject_id'), 'class_id' => (int) $request->validated('class_id'), 'counts' => $result->counts]);

        return redirect()->back()
            ->with('term_results_outcome', ['mode' => $mode, 'counts' => $result->counts, 'outcomes' => $result->outcomes])
            ->with($result->success ? 'success' : 'error', $result->success ? "flash.results.batch.{$mode}" : 'flash.results.batch.partial');
    }
}
