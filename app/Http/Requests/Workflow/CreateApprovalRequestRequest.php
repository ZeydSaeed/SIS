<?php

namespace App\Http\Requests\Workflow;

use App\Domain\Workflow\Support\ApprovalFlowEntityTypes;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateApprovalRequestRequest extends FormRequest
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
            'flow_id' => ['required', 'integer', 'min:1'],
            'entity_type' => ['required', 'string', Rule::in(ApprovalFlowEntityTypes::ALLOWED)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
            'approve' => ['prohibited'],
            'reject' => ['prohibited'],
            'current_step' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
