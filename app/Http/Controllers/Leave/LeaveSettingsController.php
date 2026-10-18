<?php

namespace App\Http\Controllers\Leave;

use App\Application\Leave\Commands\AddSuggestedLeaveTypesCommand;
use App\Application\Leave\Commands\AddSuggestedLeaveTypesHandler;
use App\Application\Leave\Commands\AdjustLeaveBalanceCommand;
use App\Application\Leave\Commands\AdjustLeaveBalanceHandler;
use App\Application\Leave\Commands\ChangeLeaveTypeStatusCommand;
use App\Application\Leave\Commands\ChangeLeaveTypeStatusHandler;
use App\Application\Leave\Commands\GrantLeaveEntitlementsCommand;
use App\Application\Leave\Commands\GrantLeaveEntitlementsHandler;
use App\Application\Leave\Commands\PublishLeavePolicyCommand;
use App\Application\Leave\Commands\PublishLeavePolicyHandler;
use App\Application\Leave\Commands\SaveLeaveTypeCommand;
use App\Application\Leave\Commands\SaveLeaveTypeHandler;
use App\Application\Leave\Results\LeaveResult;
use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveTypeFields;
use App\Domain\Leave\ValueObjects\DayBasis;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\LeaveUnit;
use App\Domain\Leave\ValueObjects\RequesterKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ManageLeaveBalanceRequest;
use App\Http\Requests\Leave\ManageLeaveTypeRequest;
use App\Http\Support\LeaveRequestContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** «أنواع الإجازات والسياسات» and «أرصدة الإجازات» writes — thin; rules live in the handlers. */
final class LeaveSettingsController extends Controller
{
    public function __construct(
        private readonly LeaveRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function storeType(ManageLeaveTypeRequest $request, SaveLeaveTypeHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new SaveLeaveTypeCommand(
            $this->context->schoolId(), null, (string) $request->validated('code'), $this->fields($request), $this->terms($request),
            $this->context->userId($request), $this->key($request),
        ));

        return $this->done($request, 'leave.web.types.store', 'type:'.$result->get('type_id'), $result, 'flash.leave.typeSaved');
    }

    public function updateType(ManageLeaveTypeRequest $request, int $type, SaveLeaveTypeHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new SaveLeaveTypeCommand(
            $this->context->schoolId(), $type, null, $this->fields($request), null, $this->context->userId($request), $this->key($request),
        ));

        return $this->done($request, 'leave.web.types.update', 'type:'.$type, $result, 'flash.leave.typeSaved');
    }

    public function typeStatus(ManageLeaveTypeRequest $request, int $type, ChangeLeaveTypeStatusHandler $handler): RedirectResponse
    {
        $active = (int) $request->validated('active') === 1;
        $result = $handler->handle(new ChangeLeaveTypeStatusCommand($this->context->schoolId(), $type, $active, $this->context->userId($request), $this->key($request)));

        return $this->done($request, 'leave.web.types.status', 'type:'.$type, $result, $active ? 'flash.leave.typeReactivated' : 'flash.leave.typeDeactivated');
    }

    public function publishPolicy(ManageLeaveTypeRequest $request, int $type, PublishLeavePolicyHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new PublishLeavePolicyCommand($this->context->schoolId(), $type, $this->terms($request), $this->context->userId($request), $this->key($request)));

        return $this->done($request, 'leave.web.types.policy', 'type:'.$type, $result, 'flash.leave.policyPublished');
    }

    public function addSuggested(ManageLeaveTypeRequest $request, AddSuggestedLeaveTypesHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new AddSuggestedLeaveTypesCommand($this->context->schoolId(), $this->context->userId($request), $this->key($request)));

        return $this->done($request, 'leave.web.types.suggested', 'school:'.$this->context->schoolId(), $result, 'flash.leave.suggestedAdded?count='.(int) $result->get('added'));
    }

    public function adjust(ManageLeaveBalanceRequest $request, AdjustLeaveBalanceHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new AdjustLeaveBalanceCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (int) $request->validated('leave_type_id'),
            RequesterKind::from((int) $request->validated('requester_kind')), (int) $request->validated('person_id'),
            (float) $request->validated('quantity'), (string) $request->validated('reason'), $this->context->userId($request), $this->key($request),
        ));

        return $this->done($request, 'leave.web.balances.adjust', 'balance_entry:'.$result->get('entry_id'), $result, 'flash.leave.adjusted');
    }

    public function grant(ManageLeaveBalanceRequest $request, GrantLeaveEntitlementsHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new GrantLeaveEntitlementsCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (int) $request->validated('leave_type_id'),
            $this->context->userId($request), $this->key($request),
        ));

        return $this->done($request, 'leave.web.balances.grant', 'type:'.$request->validated('leave_type_id'), $result, 'flash.leave.granted?count='.(int) $result->get('granted'));
    }

    private function fields(ManageLeaveTypeRequest $request): LeaveTypeFields
    {
        return new LeaveTypeFields(
            (string) $request->validated('name_ar'),
            $request->validated('name_en'),
            $request->validated('description'),
            LeaveAudience::from((int) $request->validated('applies_to')),
            LeaveUnit::from((int) $request->validated('unit')),
            (bool) $request->validated('is_sensitive'),
            $request->validated('color_hue') !== null ? (int) $request->validated('color_hue') : null,
        );
    }

    private function terms(ManageLeaveTypeRequest $request): LeavePolicyTerms
    {
        $quantity = static fn (mixed $v): ?float => $v === null || $v === '' ? null : (float) $v;

        return new LeavePolicyTerms(
            DayBasis::from((int) $request->validated('day_basis')),
            (bool) $request->validated('requires_document'),
            (bool) $request->validated('deducts_balance'),
            (int) $request->validated('approval_levels'),
            $quantity($request->validated('max_per_request')),
            $quantity($request->validated('annual_entitlement')),
        );
    }

    private function key(Request $request): string
    {
        return trim((string) $request->header('X-Idempotency-Key'));
    }

    private function done(Request $request, string $action, string $target, LeaveResult $result, string $flash): RedirectResponse
    {
        $this->securityAudit->record(SecurityEventType::HrDataModified, $action, 'success', $request->user(), $target, ['from_idempotency' => $result->fromIdempotencyCache]);

        return back()->with('success', $flash);
    }
}
