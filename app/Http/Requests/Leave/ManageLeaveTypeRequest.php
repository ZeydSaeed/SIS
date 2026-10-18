<?php

namespace App\Http\Requests\Leave;

use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveTypeFields;
use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * «أنواع الإجازات والسياسات» writes (holders of `leave.policy.manage`). Fields per action come from the route name:
 * a new type carries its code and first policy; a policy change is always a new version.
 */
class ManageLeaveTypeRequest extends FormRequest
{
    use RequiresOrganizationIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageLeavePolicies') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = match ($this->route()?->getName()) {
            'leave.types.store' => ['code' => ['required', 'string', 'regex:/^[A-Za-z0-9_]{2,30}$/']] + $this->typeRules() + $this->policyRules(),
            'leave.types.update' => $this->typeRules(),
            'leave.types.policy' => $this->policyRules(),
            'leave.types.status' => ['active' => ['required', 'integer', 'in:0,1']],
            'leave.types.suggested' => [],
            default => [],
        };

        return array_merge($fields, ['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    /** @return array<string, mixed> */
    private function typeRules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:'.LeaveTypeFields::MAX_NAME],
            'name_en' => ['nullable', 'string', 'max:'.LeaveTypeFields::MAX_NAME],
            'description' => ['nullable', 'string', 'max:2000'],
            'applies_to' => ['required', 'integer', 'in:1,2,3'],
            'unit' => ['required', 'integer', 'in:1,2'],
            'is_sensitive' => ['required', 'boolean'],
            'color_hue' => ['nullable', 'integer', 'between:0,359'],
        ];
    }

    /** @return array<string, mixed> */
    private function policyRules(): array
    {
        $quantity = ['nullable', 'numeric', 'min:0', 'max:'.LeavePolicyTerms::MAX_QUANTITY];

        return [
            'day_basis' => ['required', 'integer', 'in:1,2'],
            'requires_document' => ['required', 'boolean'],
            'deducts_balance' => ['required', 'boolean'],
            'approval_levels' => ['required', 'integer', 'between:1,3'],
            'max_per_request' => $quantity,
            'annual_entitlement' => $quantity,
        ];
    }
}
