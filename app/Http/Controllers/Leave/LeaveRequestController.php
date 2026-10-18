<?php

namespace App\Http\Controllers\Leave;

use App\Application\Leave\Commands\AttachLeaveDocumentCommand;
use App\Application\Leave\Commands\AttachLeaveDocumentHandler;
use App\Application\Leave\Commands\CancelLeaveRequestCommand;
use App\Application\Leave\Commands\CancelLeaveRequestHandler;
use App\Application\Leave\Commands\DecideLeaveRequestCommand;
use App\Application\Leave\Commands\DecideLeaveRequestHandler;
use App\Application\Leave\Commands\SaveLeaveRequestCommand;
use App\Application\Leave\Commands\SaveLeaveRequestHandler;
use App\Application\Leave\Commands\SubmitLeaveRequestCommand;
use App\Application\Leave\Commands\SubmitLeaveRequestHandler;
use App\Application\Leave\Commands\WithdrawLeaveRequestCommand;
use App\Application\Leave\Commands\WithdrawLeaveRequestHandler;
use App\Application\Leave\Results\LeaveResult;
use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\ValueObjects\LeaveDecision;
use App\Domain\Leave\ValueObjects\RequesterKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\AttachLeaveDocumentRequest;
use App\Http\Requests\Leave\LeaveRequestActionRequest;
use App\Http\Requests\Leave\SaveLeaveRequestRequest;
use App\Http\Support\LeaveRequestContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * «طلبات الإجازات» writes. Thin: the FormRequest authorizes and validates, the handler owns the transaction and
 * every rule (own request, transitions, self-approval, balance, overlap). A broken rule comes back as a
 * localized flash error; the reason text is never written to the security audit.
 */
final class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function store(SaveLeaveRequestRequest $request, SaveLeaveRequestHandler $handler, AttachLeaveDocumentHandler $attach, SubmitLeaveRequestHandler $submit): RedirectResponse
    {
        $result = $handler->handle($this->saveCommand($request, null));

        return $this->afterSave($request, 'leave.web.requests.store', 'created', $result, $attach, $submit);
    }

    public function update(SaveLeaveRequestRequest $request, int $leaveRequest, SaveLeaveRequestHandler $handler, AttachLeaveDocumentHandler $attach, SubmitLeaveRequestHandler $submit): RedirectResponse
    {
        $result = $handler->handle($this->saveCommand($request, $leaveRequest));

        return $this->afterSave($request, 'leave.web.requests.update', 'updated', $result, $attach, $submit);
    }

    /**
     * One form, up to three commands (each with its own transaction and key): save, attach the file if one came,
     * submit if asked. A refused submission leaves the saved draft and its attachment in place.
     */
    private function afterSave(SaveLeaveRequestRequest $request, string $action, string $outcome, LeaveResult $saved, AttachLeaveDocumentHandler $attach, SubmitLeaveRequestHandler $submit): RedirectResponse
    {
        $this->done($request, $action, $outcome, $saved, 'flash.leave.saved');
        $id = (int) $saved->get('request_id');
        $canManage = (bool) $request->user()?->can('manageLeaveRequests');
        $file = $request->file('document');
        if ($file !== null) {
            $this->done($request, 'leave.web.requests.attach', 'attached', $attach->handle(new AttachLeaveDocumentCommand(
                $this->context->schoolId(), $id, $this->fileOf($file), $this->context->userId($request), $canManage, $this->key($request).':attach',
            )), 'flash.leave.attached');
        }
        if (! $request->boolean('submit')) {
            return back()->with('success', 'flash.leave.saved');
        }

        return $this->done($request, 'leave.web.requests.submit', 'submitted', $submit->handle(new SubmitLeaveRequestCommand(
            $this->context->schoolId(), $id, (int) $saved->get('version'), $this->context->userId($request), $canManage, $this->key($request).':submit',
        )), 'flash.leave.submitted');
    }

    public function submit(LeaveRequestActionRequest $request, int $leaveRequest, SubmitLeaveRequestHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new SubmitLeaveRequestCommand(
            $this->context->schoolId(), $leaveRequest, (int) $request->validated('version'), $this->context->userId($request),
            (bool) $request->user()?->can('manageLeaveRequests'), $this->key($request),
        ));

        return $this->done($request, 'leave.web.requests.submit', 'submitted', $result, 'flash.leave.submitted');
    }

    public function decide(LeaveRequestActionRequest $request, int $leaveRequest, DecideLeaveRequestHandler $handler): RedirectResponse
    {
        $decision = LeaveDecision::from((string) $request->validated('decision'));
        $result = $handler->handle(new DecideLeaveRequestCommand(
            $this->context->schoolId(), $leaveRequest, (int) $request->validated('version'), $decision,
            $request->validated('note'), $this->context->userId($request), $this->key($request),
        ));
        $flash = match ($decision) {
            LeaveDecision::Approve => (int) $result->get('status') === 4 ? 'flash.leave.approved' : 'flash.leave.levelApproved',
            LeaveDecision::Reject => 'flash.leave.rejected',
            LeaveDecision::RequestInfo => 'flash.leave.infoRequested',
        };

        return $this->done($request, 'leave.web.requests.decide', $decision->value, $result, $flash);
    }

    public function withdraw(LeaveRequestActionRequest $request, int $leaveRequest, WithdrawLeaveRequestHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new WithdrawLeaveRequestCommand(
            $this->context->schoolId(), $leaveRequest, (int) $request->validated('version'), $request->validated('note'),
            $this->context->userId($request), (bool) $request->user()?->can('manageLeaveRequests'), $this->key($request),
        ));

        return $this->done($request, 'leave.web.requests.withdraw', 'withdrawn', $result, 'flash.leave.withdrawn');
    }

    public function cancel(LeaveRequestActionRequest $request, int $leaveRequest, CancelLeaveRequestHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CancelLeaveRequestCommand(
            $this->context->schoolId(), $leaveRequest, (int) $request->validated('version'), (string) $request->validated('note'),
            $this->context->userId($request), $this->key($request),
        ));

        return $this->done($request, 'leave.web.requests.cancel', 'cancelled', $result, 'flash.leave.cancelled');
    }

    public function attach(AttachLeaveDocumentRequest $request, int $leaveRequest, AttachLeaveDocumentHandler $handler): RedirectResponse
    {
        $file = $request->file('document');
        $result = $handler->handle(new AttachLeaveDocumentCommand(
            $this->context->schoolId(), $leaveRequest, $this->fileOf($file),
            $this->context->userId($request), (bool) $request->user()?->can('manageLeaveRequests'), $this->key($request),
        ));

        return $this->done($request, 'leave.web.requests.attach', 'attached', $result, 'flash.leave.attached');
    }

    private function saveCommand(SaveLeaveRequestRequest $request, ?int $requestId): SaveLeaveRequestCommand
    {
        $hours = $request->validated('hours');

        return new SaveLeaveRequestCommand(
            schoolId: $this->context->schoolId(),
            academicYearId: $this->context->academicYearId($request),
            requestId: $requestId,
            expectedVersion: $request->validated('version') !== null ? (int) $request->validated('version') : null,
            kind: RequesterKind::from((int) $request->validated('requester_kind')),
            personId: (int) $request->validated('person_id'),
            draft: new LeaveDraft(
                (int) $request->validated('leave_type_id'),
                (string) $request->validated('date_from'),
                (string) $request->validated('date_to'),
                $hours !== null ? (float) $hours : null,
                (string) $request->validated('reason'),
            ),
            actorUserId: $this->context->userId($request),
            canManage: (bool) $request->user()?->can('manageLeaveRequests'),
            idempotencyKey: $this->key($request),
        );
    }

    /** @return array{name: string, mime: string, contents: string} */
    private function fileOf(UploadedFile $file): array
    {
        return [
            'name' => (string) $file->getClientOriginalName(),
            'mime' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
            'contents' => (string) file_get_contents($file->getRealPath()),
        ];
    }

    private function key(Request $request): string
    {
        return trim((string) $request->header('X-Idempotency-Key'));
    }

    private function done(Request $request, string $action, string $outcome, LeaveResult $result, string $flash): RedirectResponse
    {
        $this->securityAudit->record(SecurityEventType::HrDataModified, $action, $outcome, $request->user(), 'leave_request:'.$result->get('request_id'), [
            'status' => $result->get('status'),
            'from_idempotency' => $result->fromIdempotencyCache,
        ]);

        return back()->with('success', $flash);
    }
}
