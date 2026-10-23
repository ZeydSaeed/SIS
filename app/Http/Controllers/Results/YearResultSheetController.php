<?php

namespace App\Http\Controllers\Results;

use App\Application\Results\Commands\BuildRankingSnapshotCommand;
use App\Application\Results\Commands\BuildRankingSnapshotHandler;
use App\Application\Results\Commands\IssueTranscriptsBatchCommand;
use App\Application\Results\Commands\IssueTranscriptsBatchHandler;
use App\Application\Results\Commands\ProcessTermResultsBatchCommand;
use App\Application\Results\Commands\ProcessYearResultsBatchCommand;
use App\Application\Results\Commands\ProcessYearResultsBatchHandler;
use App\Application\Results\Queries\GetYearResultSheetHandler;
use App\Application\Results\Queries\GetYearResultSheetQuery;
use App\Application\Results\Support\TermResultsBatchTargetValidator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\BuildRankingSnapshotRequest;
use App\Http\Requests\Results\CalculateYearResultsBatchRequest;
use App\Http\Requests\Results\FinalizeYearResultsBatchRequest;
use App\Http\Requests\Results\IssueTranscriptsBatchRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «النتيجة السنوية والمعدل» — annual results and year GPA of a class / section, calculated and finalized per student;
 * the class ranking and the students' transcripts are issued from the same sheet.
 */
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
            ? ['classes' => [], 'rows' => [], 'ranking' => null]
            : $handler->handle(new GetYearResultSheetQuery($this->schoolContext->requireId(), $academicYearId, $filters['class_id'], $filters['section_id']));

        $this->securityAudit->record(SecurityEventType::ResultsDataAccess, 'results.web.year_sheet', 'allowed', $user, 'annual_results', ['rows' => count($sheet['rows'])]);

        return Inertia::render('results/year-sheet', $sheet + [
            'filters' => $filters,
            'abilities' => [
                'calculate' => $user->can('calculate', TermResultRecord::class),
                'finalize' => $user->can('finalize', TermResultRecord::class),
                'buildRanking' => $user->can('buildRanking', TermResultRecord::class),
                'issueTranscript' => $user->can('issueTranscript', TermResultRecord::class),
            ],
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

    /** The ranking of the whole class (official year GPAs), after checking the class is of the school-year. */
    public function ranking(BuildRankingSnapshotRequest $request, TermResultsBatchTargetValidator $target, BuildRankingSnapshotHandler $handler): RedirectResponse
    {
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = (int) $request->validated('academic_year_id');
        $classId = (int) $request->validated('class_id');
        $target->assertClassAndSection($schoolId, $academicYearId, $classId, null);
        $result = $handler->handle(new BuildRankingSnapshotCommand($schoolId, $academicYearId, $classId, trim((string) $request->header('X-Idempotency-Key')), $request->user()?->id, CorrelationContext::id()));

        $this->securityAudit->record(SecurityEventType::ResultsDataModified, 'results.web.year_sheet.ranking', 'built', $request->user(), 'ranking_snapshot:'.($result->rankingSnapshotId ?? 'unknown'), ['class_id' => $classId]);

        return redirect()->back()->with('success', 'flash.results.year.ranking');
    }

    public function transcripts(IssueTranscriptsBatchRequest $request, IssueTranscriptsBatchHandler $handler): RedirectResponse
    {
        $sectionId = $request->validated('section_id');
        $result = $handler->handle(new IssueTranscriptsBatchCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            classId: (int) $request->validated('class_id'),
            sectionId: $sectionId !== null ? (int) $sectionId : null,
            actorUserId: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(SecurityEventType::ResultsDataModified, 'results.web.year_batch.transcripts', $result->success ? 'completed' : 'partial', $request->user(),
            'transcripts', ['class_id' => (int) $request->validated('class_id'), 'counts' => $result->counts]);

        return redirect()->back()
            ->with('year_results_outcome', ['target' => 'transcript', 'mode' => 'issue', 'counts' => $result->counts, 'outcomes' => $result->outcomes])
            ->with($result->success ? 'success' : 'error', $result->success ? 'flash.results.year.transcripts' : 'flash.results.batch.partial');
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
