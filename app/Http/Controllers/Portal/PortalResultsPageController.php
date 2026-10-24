<?php

namespace App\Http\Controllers\Portal;

use App\Application\Results\Queries\GetPortalPublishedResultsHandler;
use App\Application\Results\Queries\GetPortalPublishedResultsQuery;
use App\Http\Controllers\Controller;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\PortalPartyAccessService;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «نتائجي» — the published annual results and year GPA of the student (or of the guardian's students). */
final class PortalResultsPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly PortalPartyAccessService $portalAccess,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function show(Request $request, GetPortalPublishedResultsHandler $handler): Response
    {
        $this->authorize('viewPortalOfficial');
        $user = $request->user();
        assert($user !== null);

        $rows = $handler->handle(new GetPortalPublishedResultsQuery($this->schoolContext->requireId(), $this->portalAccess->allowedStudentIds($user)));
        $this->securityAudit->record(SecurityEventType::ResultsDataAccess, 'portal.results.web.show', 'viewed', $user, 'published_results', ['rows' => count($rows)]);

        return Inertia::render('portal/results', ['rows' => $rows]);
    }
}
