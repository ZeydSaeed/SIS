<?php

namespace App\Http\Requests\Leave;

use App\Http\Requests\Organization\RequiresOrganizationIdempotencyKey;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** One attachment (PDF or image, ≤ 10 MB; the Documents module checks size and MIME again from the content). */
class AttachLeaveDocumentRequest extends FormRequest
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
            'document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
