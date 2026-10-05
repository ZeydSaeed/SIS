<?php

namespace App\Http\Controllers\Admission;

use App\Application\Admission\Commands\TransferApplicationCommand;
use App\Application\Admission\Commands\TransferApplicationHandler;
use App\Application\Admission\Queries\GetApplicationTransfersHandler;
use App\Application\Admission\Queries\GetApplicationTransfersQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\TransferApplicationRequest;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transfers page (النقل) — the only place an admission application's school,
 * request kind or academic year can change.
 */
final class ApplicationTransferPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SchoolScopeService $schoolScope,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, GetApplicationTransfersHandler $handler): Response
    {
        $user = $request->user();
        assert($user !== null);

        if (! $user->can('viewAdmission')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $search = $request->filled('q') ? mb_substr(trim((string) $request->query('q')), 0, 80) : null;
        $data = $handler->handle(new GetApplicationTransfersQuery(
            schoolId: $this->schoolContext->requireId(),
            allowedSchoolIds: $this->schoolScope->allowedSchoolIds($user),
            search: $search === '' ? null : $search,
            page: max(1, (int) $request->query('page', 1)),
        ));

        return Inertia::render('transfers/index', [
            'transfers' => $data,
            'filters' => ['q' => $search],
            'authorization' => ['can_manage' => $user->can('manageAdmission')],
        ]);
    }

    public function store(int $application, TransferApplicationRequest $request, TransferApplicationHandler $handler): RedirectResponse
    {
        $user = $request->user();
        assert($user !== null);

        $result = $handler->handle(new TransferApplicationCommand(
            schoolId: $this->schoolContext->requireId(),
            applicationId: $application,
            toSchoolId: (int) $request->validated('target_school_id'),
            toRequestKind: (int) $request->validated('request_kind'),
            toPeriodId: (int) $request->validated('application_period_id'),
            reason: $this->nullableText($request->validated('reason')),
            transferredBy: (int) $user->id,
            allowedSchoolIds: $this->schoolScope->allowedSchoolIds($user),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'transfer' => $result->errors[0] ?? 'admission.transfer_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.application.transfer',
            'transferred',
            $user,
            "admission_application:{$application}",
            [
                'to_school_id' => (int) $request->validated('target_school_id'),
                'to_request_kind' => (int) $request->validated('request_kind'),
                'to_period_id' => (int) $request->validated('application_period_id'),
            ],
        );

        return redirect()->back()->with('success', 'flash.admission.applicationTransferred');
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
