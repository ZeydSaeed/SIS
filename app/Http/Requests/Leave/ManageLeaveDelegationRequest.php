<?php

namespace App\Http\Requests\Leave;

use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Approver delegation: only a holder of the approval permission itself delegates (a delegate cannot pass it on);
 * the delegator or a leave manager revokes. Dates are validated again by the handler.
 */
class ManageLeaveDelegationRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return match ($this->route()?->getName()) {
            'leave.delegations.store' => $user->can('holdLeaveApproval'),
            'leave.delegations.revoke' => $user->can('holdLeaveApproval') || $user->can('manageLeaveRequests'),
            default => false,
        };
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = $this->route()?->getName() === 'leave.delegations.store'
            ? [
                'delegate_user_id' => ['required', 'integer', 'min:1'],
                'date_from' => ['required', 'date_format:Y-m-d'],
                'date_to' => ['required', 'date_format:Y-m-d'],
            ]
            : [];

        return array_merge($fields, ['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
