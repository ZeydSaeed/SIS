<?php

namespace App\Http\Requests\Workflow;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateApprovalFlowRequest extends FormRequest
{
    use RequiresWorkflowIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageWorkflow') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'is_active' => ['prohibited'],
            'school_id' => ['prohibited'],
            'steps' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
