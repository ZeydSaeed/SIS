<?php

namespace App\Http\Controllers\Results;

use App\Application\Results\Commands\PublishYearResultsCommand;
use App\Application\Results\Commands\PublishYearResultsHandler;
use App\Application\Results\Contracts\ResultPublicationRepositoryInterface;
use App\Application\Results\Queries\GetTermResultSheetHandler;
use App\Application\Results\Queries\GetTermResultSheetQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Results\PublishTermResultsRequest;
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
 * «نشر نتائج الفصول» (EXAMIS U-03) — publish the official term results of one term and subject for a class or section.
 * Same handler and rules as the annual publication (kind = term); withdrawal goes through ResultPublicationController.
 */
final class TermResultPublicationController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, GetTermResultSheetHandler $handler, ResultPublicationRepositoryInterface $publications): Response
    {
        $this->authorize('viewOfficial', TermResultRecord::class);
        $user = $request->user();
        assert($user !== null);
        $schoolId = $this->schoolContext->requireId();
        $int = static fn (string $key): ?int => $request->filled($key) ? (int) $request->query($key) : null;
        $academicYearId = $this->academicYears->resolve($int('academic_year_id'));
        $filters = [
            'academic_year_id' => $academicYearId, 'term_id' => $int('term_id'), 'subject_id' => $int('subject_id'),
            'class_id' => $int('class_id'), 'section_id' => $int('section_id'),
        ];

        $sheet = $academicYearId === null
            ? ['terms' => [], 'classes' => [], 'subjects' => [], 'rows' => [], 'reconciliation' => ['students' => 0, 'issues' => [], 'ready' => []]]
            : $handler->handle(new GetTermResultSheetQuery($schoolId, $academicYearId, $filters['term_id'], $filters['subject_id'], $filters['class_id'], $filters['section_id']));

        $this->securityAudit->record(SecurityEventType::ResultsDataAccess, 'results.web.term_publications', 'allowed', $user, 'result_publications', ['rows' => count($sheet['rows'])]);

        return Inertia::render('results/term-publications', [
            'filters' => $filters,
            'terms' => $sheet['terms'],
            'subjects' => $sheet['subjects'],
            'classes' => $sheet['classes'],
            'rows' => array_map(static fn (array $r): array => [
                'enrollment_id' => $r['enrollment_id'],
                'student_name' => $r['student_name'],
                'student_code' => $r['student_code'],
                'section_name' => $r['section_name'],
                'official' => $r['official'],
            ], $sheet['rows']),
            'reconciliation' => $sheet['reconciliation'],
            'publications' => $academicYearId === null || $filters['class_id'] === null
                ? []
                : array_values(array_filter(
                    $publications->activeForClass($schoolId, $academicYearId, $filters['class_id']),
                    static fn (array $p): bool => $p['kind'] === 'term' && $p['term_id'] === $filters['term_id'] && $p['subject_id'] === $filters['subject_id'],
                )),
            'abilities' => ['publish' => $user->can('publish', TermResultRecord::class)],
        ]);
    }

    public function store(PublishTermResultsRequest $request, PublishYearResultsHandler $handler): RedirectResponse
    {
        $sectionId = $request->validated('section_id');
        $classId = (int) $request->validated('class_id');
        $termId = (int) $request->validated('term_id');
        $subjectId = (int) $request->validated('subject_id');
        $result = $handler->handle(new PublishYearResultsCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            classId: $classId,
            sectionId: $sectionId !== null ? (int) $sectionId : null,
            kind: 'term',
            actorUserId: $request->user()?->id,
            correlationId: CorrelationContext::id(),
            termId: $termId,
            subjectId: $subjectId,
        ));

        $this->securityAudit->record(SecurityEventType::ResultsDataModified, 'results.web.term_publications.publish', 'published', $request->user(),
            'result_publication:'.$result->publicationId, ['class_id' => $classId, 'section_id' => $sectionId, 'term_id' => $termId, 'subject_id' => $subjectId, 'participants' => $result->participants]);

        return redirect()->back()->with('success', 'flash.results.batch.publish');
    }
}
