<?php

namespace App\Http\Requests\Transfers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class DecideTransferRequestRequest extends FormRequest
{
    use RequiresTransferIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manageTransfers') ?? false;
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
