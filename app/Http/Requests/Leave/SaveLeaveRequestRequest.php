<?php

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Services\LeaveRequestRules;
use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit a leave request. Who may: the staff member themselves (`leave.request.create`) or a holder of
 * `leave.request.manage`; that the person is the user's own is checked by the Application layer. The school and
 * the requester's account are never fields.
 */
class SaveLeaveRequestRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('requestLeave') || $user->can('manageLeaveRequests'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['nullable', 'integer', 'min:1'],
            'requester_kind' => ['required', 'integer', 'in:1,2'],
            'person_id' => ['required', 'integer', 'min:1'],
            'leave_type_id' => ['required', 'integer', 'min:1'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'hours' => ['nullable', 'numeric', 'min:0.25', 'max:'.LeaveRequestRules::MAX_HOURS],
            'reason' => ['required', 'string', 'max:'.LeaveRequestRules::MAX_REASON],
            'version' => [$this->route('leaveRequest') === null ? 'nullable' : 'required', 'integer', 'min:1'],
            'submit' => ['nullable', 'boolean'],
            'document' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
