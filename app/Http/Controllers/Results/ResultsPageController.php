<?php

namespace App\Http\Controllers\Results;

use App\Application\Results\DTOs\OfficialTermResultListItemDTO;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataHandler;
use App\Application\Results\Queries\GetIssuedTranscriptMetadataQuery;
use App\Application\Results\Queries\GetOfficialAnnualResultHandler;
use App\Application\Results\Queries\GetOfficialAnnualResultQuery;
use App\Application\Results\Queries\GetOfficialTermResultHandler;
use App\Application\Results\Queries\GetOfficialTermResultQuery;
use App\Application\Results\Queries\GetOfficialYearGpaHandler;
use App\Application\Results\Queries\GetOfficialYearGpaQuery;
use App\Application\Results\Queries\ListOfficialTermResultsHandler;
use App\Application\Results\Queries\ListOfficialTermResultsQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ResultsPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListOfficialTermResultsHandler $handler): Response
    {
        $this->authorize('viewOfficial', TermResultRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $enrollmentId = $request->filled('enrollment_id')
            ? (int) $request->query('enrollment_id')
            : null;

        $rows = [];
        if ($academicYearId !== null && $enrollmentId !== null && $enrollmentId > 0) {
            $items = $handler->handle(new ListOfficialTermResultsQuery(
                schoolId: $schoolId,
                enrollmentId: $enrollmentId,
                academicYearId: $academicYearId,
            ));
            $rows = array_map(
                static fn (OfficialTermResultListItemDTO $dto): array => [
                    'school_id' => $dto->schoolId,
                    'enrollment_id' => $dto->enrollmentId,
                    'academic_year_id' => $dto->academicYearId,
                    'term_result_id' => $dto->termResultId,
                    'term_id' => $dto->termId,
                    'subject_id' => $dto->subjectId,
                    'weighted_total' => $dto->weightedTotal,
                    'pass_fail' => $dto->passFail,
                    'incomplete' => $dto->incomplete,
                ],
                $items,
            );
        }

        $this->securityAudit->record(
            SecurityEventType::ResultsDataAccess,
            'results.web.terms.index',
            'allowed',
            $user,
            'term_results',
            [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
                'count' => count($rows),
            ],
        );

        return Inertia::render('results/index', [
            'results' => [
                'data' => $rows,
            ],
            'filters' => [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
            ],
        ]);
    }

    public function show(
        Request $request,
        ListOfficialTermResultsHandler $listHandler,
        GetOfficialAnnualResultHandler $annualHandler,
        GetOfficialYearGpaHandler $gpaHandler,
    ): Response {
        $this->authorize('viewOfficial', TermResultRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $enrollmentId = $request->filled('enrollment_id')
            ? (int) $request->query('enrollment_id')
            : null;

        $terms = [];
        $annual = null;
        $gpa = null;

        if ($academicYearId !== null && $enrollmentId !== null && $enrollmentId > 0) {
            $terms = array_map(
                static fn (OfficialTermResultListItemDTO $dto): array => [
                    'term_result_id' => $dto->termResultId,
                    'term_id' => $dto->termId,
                    'subject_id' => $dto->subjectId,
                    'weighted_total' => $dto->weightedTotal,
                    'pass_fail' => $dto->passFail,
                    'incomplete' => $dto->incomplete,
                ],
                $listHandler->handle(new ListOfficialTermResultsQuery(
                    schoolId: $schoolId,
                    enrollmentId: $enrollmentId,
                    academicYearId: $academicYearId,
                )),
            );

            $annualDto = $annualHandler->handle(new GetOfficialAnnualResultQuery(
                schoolId: $schoolId,
                enrollmentId: $enrollmentId,
                academicYearId: $academicYearId,
            ));
            if ($annualDto !== null) {
                $annual = [
                    'annual_result_id' => $annualDto->annualResultId,
                    'result_version' => $annualDto->resultVersion,
                    'average_weighted_total' => $annualDto->averageWeightedTotal,
                    'incomplete' => $annualDto->incomplete,
                ];
            }

            $gpaDto = $gpaHandler->handle(new GetOfficialYearGpaQuery(
                schoolId: $schoolId,
                enrollmentId: $enrollmentId,
                academicYearId: $academicYearId,
            ));
            if ($gpaDto !== null) {
                $gpa = [
                    'gpa_result_id' => $gpaDto->gpaResultId,
                    'result_version' => $gpaDto->resultVersion,
                    'gpa_value' => $gpaDto->gpaValue,
                    'scale_code' => $gpaDto->scaleCode,
                    'incomplete' => $gpaDto->incomplete,
                ];
            }
        }

        $this->securityAudit->record(
            SecurityEventType::ResultsDataAccess,
            'results.web.show',
            'allowed',
            $user,
            'results_summary',
            [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
            ],
        );

        return Inertia::render('results/show', [
            'terms' => $terms,
            'annual' => $annual,
            'gpa' => $gpa,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
            ],
        ]);
    }

    public function term(
        Request $request,
        GetOfficialTermResultHandler $handler,
    ): Response {
        $this->authorize('viewOfficial', TermResultRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $enrollmentId = $request->filled('enrollment_id') ? (int) $request->query('enrollment_id') : null;
        $termId = $request->filled('term_id') ? (int) $request->query('term_id') : null;
        $subjectId = $request->filled('subject_id') ? (int) $request->query('subject_id') : null;

        $term = null;
        if (
            $academicYearId !== null
            && $enrollmentId !== null && $enrollmentId > 0
            && $termId !== null && $termId > 0
            && $subjectId !== null && $subjectId > 0
        ) {
            $dto = $handler->handle(new GetOfficialTermResultQuery(
                schoolId: $schoolId,
                enrollmentId: $enrollmentId,
                academicYearId: $academicYearId,
                termId: $termId,
                subjectId: $subjectId,
            ));
            if ($dto !== null) {
                $term = [
                    'school_id' => $dto->schoolId,
                    'enrollment_id' => $dto->enrollmentId,
                    'academic_year_id' => $dto->academicYearId,
                    'term_id' => $dto->termId,
                    'subject_id' => $dto->subjectId,
                    'term_result_id' => $dto->termResultId,
                    'result_version' => $dto->resultVersion,
                    'weighted_total' => $dto->weightedTotal,
                    'incomplete' => $dto->incomplete,
                    'source_fingerprint' => $dto->sourceFingerprint,
                ];
            }
        }

        $this->securityAudit->record(
            SecurityEventType::ResultsDataAccess,
            'results.web.term.show',
            'allowed',
            $user,
            'term_result',
            [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
                'term_id' => $termId,
                'subject_id' => $subjectId,
            ],
        );

        return Inertia::render('results/term', [
            'term' => $term,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
                'term_id' => $termId,
                'subject_id' => $subjectId,
            ],
        ]);
    }

    public function transcript(
        Request $request,
        GetIssuedTranscriptMetadataHandler $handler,
    ): Response {
        $this->authorize('viewOfficial', TermResultRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $enrollmentId = $request->filled('enrollment_id')
            ? (int) $request->query('enrollment_id')
            : null;

        $transcript = null;
        if ($academicYearId !== null && $enrollmentId !== null && $enrollmentId > 0) {
            $dto = $handler->handle(new GetIssuedTranscriptMetadataQuery(
                schoolId: $schoolId,
                enrollmentId: $enrollmentId,
                academicYearId: $academicYearId,
            ));
            if ($dto !== null) {
                $transcript = [
                    'school_id' => $dto->schoolId,
                    'enrollment_id' => $dto->enrollmentId,
                    'student_id' => $dto->studentId,
                    'academic_year_id' => $dto->academicYearId,
                    'transcript_id' => $dto->transcriptId,
                    'transcript_version' => $dto->transcriptVersion,
                    'transcript_number' => $dto->transcriptNumber,
                    'payload_hash' => $dto->payloadHash,
                    'issued_at' => $dto->issuedAt,
                    // storage_key omitted from UI — PDF download HOLD
                ];
            }
        }

        $this->securityAudit->record(
            SecurityEventType::ResultsDataAccess,
            'results.web.transcript.show',
            'allowed',
            $user,
            'transcript',
            [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
            ],
        );

        return Inertia::render('results/transcript', [
            'transcript' => $transcript,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'enrollment_id' => $enrollmentId,
            ],
        ]);
    }
}
