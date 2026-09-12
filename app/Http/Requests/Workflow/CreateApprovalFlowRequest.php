<?php

namespace App\Http\Requests\Workflow;

use App\Domain\Workflow\Support\ApprovalFlowEntityTypes;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateApprovalFlowRequest extends FormRequest
{
    use RequiresWorkflowIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageWorkflow') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'entity_type' => ['required', 'string', Rule::in(ApprovalFlowEntityTypes::ALLOWED)],
            'name' => ['required', 'string', 'max:255'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.step' => ['required', 'integer', 'min:1'],
            'steps.*.role' => ['required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'],
            'approve' => ['prohibited'],
            'reject' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
