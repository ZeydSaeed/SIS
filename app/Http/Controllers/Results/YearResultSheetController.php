<?php

namespace App\Http\Controllers\Results;

use App\Application\Results\Commands\ProcessTermResultsBatchCommand;
use App\Application\Results\Commands\ProcessYearResultsBatchCommand;
use App\Application\Results\Commands\ProcessYearResultsBatchHandler;
use App\Application\Results\Queries\GetYearResultSheetHandler;
use App\Application\Results\Queries\GetYearResultSheetQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\CalculateYearResultsBatchRequest;
use App\Http\Requests\Results\FinalizeYearResultsBatchRequest;
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

/** «النتيجة السنوية والمعدل» — annual results and year GPA of a class / section, calculated and finalized per student. */
final class YearResultSheetController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function show(Request $request, GetYearResultSheetHandler $handler): Response
    {
        $this->authorize('viewOfficial', TermResultRecord::class);
        $user = $request->user();
        assert($user !== null);
        $int = static fn (string $key): ?int => $request->filled($key) ? (int) $request->query($key) : null;
        $academicYearId = $this->academicYears->resolve($int('academic_year_id'));
        $filters = ['academic_year_id' => $academicYearId, 'class_id' => $int('class_id'), 'section_id' => $int('section_id')];

        $sheet = $academicYearId === null
            ? ['classes' => [], 'rows' => []]
            : $handler->handle(new GetYearResultSheetQuery($this->schoolContext->requireId(), $academicYearId, $filters['class_id'], $filters['section_id']));

        $this->securityAudit->record(SecurityEventType::ResultsDataAccess, 'results.web.year_sheet', 'allowed', $user, 'annual_results', ['rows' => count($sheet['rows'])]);

        return Inertia::render('results/year-sheet', $sheet + [
            'filters' => $filters,
            'abilities' => ['calculate' => $user->can('calculate', TermResultRecord::class), 'finalize' => $user->can('finalize', TermResultRecord::class)],
            'outcomes' => $request->session()->get('year_results_outcome'),
        ]);
    }

    public function calculate(CalculateYearResultsBatchRequest $request, ProcessYearResultsBatchHandler $handler): RedirectResponse
    {
        return $this->process($request, $handler, ProcessTermResultsBatchCommand::CALCULATE);
    }

    public function finalize(FinalizeYearResultsBatchRequest $request, ProcessYearResultsBatchHandler $handler): RedirectResponse
    {
        return $this->process($request, $handler, ProcessTermResultsBatchCommand::FINALIZE);
    }

    private function process(FormRequest $request, ProcessYearResultsBatchHandler $handler, string $mode): RedirectResponse
    {
        $sectionId = $request->validated('section_id');
        $target = (string) $request->validated('target');
        $result = $handler->handle(new ProcessYearResultsBatchCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            classId: (int) $request->validated('class_id'),
            sectionId: $sectionId !== null ? (int) $sectionId : null,
            target: $target,
            mode: $mode,
            actorUserId: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(SecurityEventType::ResultsDataModified, "results.web.year_batch.{$target}.{$mode}", $result->success ? 'completed' : 'partial', $request->user(),
            "{$target}_results", ['class_id' => (int) $request->validated('class_id'), 'counts' => $result->counts]);

        return redirect()->back()
            ->with('year_results_outcome', ['target' => $target, 'mode' => $mode, 'counts' => $result->counts, 'outcomes' => $result->outcomes])
            ->with($result->success ? 'success' : 'error', $result->success ? "flash.results.year.{$target}.{$mode}" : 'flash.results.batch.partial');
    }
}
