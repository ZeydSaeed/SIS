<?php

namespace App\Http\Controllers\Results;

use App\Application\Results\DTOs\OfficialTermResultListItemDTO;
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
}
