<?php

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Services\LeaveRequestRules;
use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Lifecycle actions on one request (submit · withdraw · decide · cancel). Each needs the version the user saw;
 * who may act is per action (route name): the requester or a manager submits / withdraws, an approver decides,
 * a holder of `leave.request.cancel` cancels an approved request.
 */
class LeaveRequestActionRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return match ($this->route()?->getName()) {
            'leave.requests.submit', 'leave.requests.withdraw' => $user->can('requestLeave') || $user->can('manageLeaveRequests'),
            'leave.requests.decide' => $user->can('approveLeave'),
            'leave.requests.cancel' => $user->can('cancelLeave'),
            default => false,
        };
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $name = $this->route()?->getName();

        return array_merge([
            'version' => ['required', 'integer', 'min:1'],
            'decision' => $name === 'leave.requests.decide' ? ['required', 'string', 'in:approve,reject,info'] : ['prohibited'],
            'note' => [$name === 'leave.requests.cancel' ? 'required' : 'nullable', 'string', 'max:'.LeaveRequestRules::MAX_REASON],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
