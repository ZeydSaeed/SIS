<?php

namespace App\Http\Requests\Workflow;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class DecideApprovalRequestRequest extends FormRequest
{
    use RequiresWorkflowIdempotencyKey;

    public function authorize(): bool
    {
        return ($this->user()?->can('manageWorkflow') ?? false)
            || ($this->user()?->can('decideWorkflow') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'school_id' => ['prohibited'],
            'current_step' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
