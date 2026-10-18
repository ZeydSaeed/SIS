<?php

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Services\LeaveRequestRules;
use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Balance writes (holders of `leave.balance.adjust`): an audited adjustment, or granting a type's annual entitlement. */
class ManageLeaveBalanceRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('adjustLeaveBalances') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = match ($this->route()?->getName()) {
            'leave.balances.adjust' => [
                'requester_kind' => ['required', 'integer', 'in:1,2'],
                'person_id' => ['required', 'integer', 'min:1'],
                'quantity' => ['required', 'numeric', 'not_in:0', 'min:-'.LeavePolicyTerms::MAX_QUANTITY, 'max:'.LeavePolicyTerms::MAX_QUANTITY],
                'reason' => ['required', 'string', 'max:'.LeaveRequestRules::MAX_REASON],
            ],
            default => [],
        };

        return array_merge($fields, [
            'academic_year_id' => ['nullable', 'integer', 'min:1'],
            'leave_type_id' => ['required', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
