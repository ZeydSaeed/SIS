<?php

namespace App\Http\Requests\Workflow;

use App\Domain\Workflow\Support\ApprovalFlowEntityTypes;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListApprovalRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewWorkflow') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'entity_type' => ['sometimes', 'string', Rule::in(ApprovalFlowEntityTypes::ALLOWED)],
            'entity_id' => ['sometimes', 'integer', 'min:1'],
            'request_status' => ['sometimes', 'integer', 'in:1,2,3,4'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
