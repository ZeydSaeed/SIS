<?php

namespace App\Http\Controllers\Results;

use App\Application\Results\Commands\PublishYearResultsCommand;
use App\Application\Results\Commands\PublishYearResultsHandler;
use App\Application\Results\Commands\WithdrawResultPublicationCommand;
use App\Application\Results\Commands\WithdrawResultPublicationHandler;
use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Application\Results\Queries\GetYearResultSheetHandler;
use App\Application\Results\Queries\GetYearResultSheetQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\PublishYearResultsRequest;
use App\Http\Requests\Results\WithdrawResultPublicationRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «نشر النتائج» (EXAMIS U-01) — publish / withdraw the official annual results and year GPAs of a class or section.
 * The rules (all or nothing, one active publication per scope, reason on withdrawal) live in the handlers.
 */
final class ResultPublicationController extends Controller
{
    /** Kinds this page lists and withdraws; term publications belong to the term sheet. */
    private const KINDS = ['annual', 'gpa', 'transcript'];

    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, GetYearResultSheetHandler $handler, ResultPublicationRepositoryInterface $publications): Response
    {
        $this->authorize('viewOfficial', TermResultRecord::class);
        $user = $request->user();
        assert($user !== null);
        $schoolId = $this->schoolContext->requireId();
        $int = static fn (string $key): ?int => $request->filled($key) ? (int) $request->query($key) : null;
        $academicYearId = $this->academicYears->resolve($int('academic_year_id'));
        $filters = ['academic_year_id' => $academicYearId, 'class_id' => $int('class_id'), 'section_id' => $int('section_id')];

        $sheet = $academicYearId === null
            ? ['classes' => [], 'rows' => [], 'reconciliation' => ['students' => 0, 'issues' => [], 'ready' => []]]
            : $handler->handle(new GetYearResultSheetQuery($schoolId, $academicYearId, $filters['class_id'], $filters['section_id']));

        $this->securityAudit->record(SecurityEventType::ResultsDataAccess, 'results.web.publications', 'allowed', $user, 'result_publications', ['rows' => count($sheet['rows'])]);

        return Inertia::render('results/publications', [
            'filters' => $filters,
            'classes' => $sheet['classes'],
            'rows' => array_map(static fn (array $r): array => [
                'enrollment_id' => $r['enrollment_id'],
                'student_name' => $r['student_name'],
                'student_code' => $r['student_code'],
                'section_name' => $r['section_name'],
                'annual' => $r['annual']['official'],
                'gpa' => $r['gpa']['official'],
                // EXAMIS U-03: the issued transcript (number, version, up to date with the official GPA) — published as is.
                'transcript' => $r['transcript'],
            ], $sheet['rows']),
            'reconciliation' => $sheet['reconciliation'],
            'publications' => $academicYearId === null || $filters['class_id'] === null
                ? []
                : array_values(array_filter(
                    $publications->activeForClass($schoolId, $academicYearId, $filters['class_id']),
                    static fn (array $p): bool => in_array($p['kind'], self::KINDS, true),
                )),
            'abilities' => ['publish' => $user->can('publish', TermResultRecord::class)],
        ]);
    }

    public function store(PublishYearResultsRequest $request, PublishYearResultsHandler $handler): RedirectResponse
    {
        $sectionId = $request->validated('section_id');
        $target = (string) $request->validated('target');
        $classId = (int) $request->validated('class_id');
        $result = $handler->handle(new PublishYearResultsCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            classId: $classId,
            sectionId: $sectionId !== null ? (int) $sectionId : null,
            kind: $target,
            actorUserId: $request->user()?->id,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(SecurityEventType::ResultsDataModified, "results.web.publications.{$target}.publish", 'published', $request->user(),
            'result_publication:'.$result->publicationId, ['class_id' => $classId, 'section_id' => $sectionId, 'participants' => $result->participants]);

        return redirect()->back()->with('success', "flash.results.year.{$target}.publish");
    }

    public function withdraw(WithdrawResultPublicationRequest $request, int $publication, WithdrawResultPublicationHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new WithdrawResultPublicationCommand(
            $this->schoolContext->requireId(),
            $publication,
            (string) $request->validated('reason'),
            $request->user()?->id,
            $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(SecurityEventType::ResultsDataModified, 'results.web.publications.withdraw', $result->fromIdempotencyCache ? 'replayed' : 'withdrawn',
            $request->user(), 'result_publication:'.$publication, []);

        return redirect()->back()->with('success', 'flash.results.year.withdraw');
    }
}
