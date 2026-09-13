<?php

namespace App\Http\Requests\Workflow;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowApprovalRequestRequest extends FormRequest
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
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
