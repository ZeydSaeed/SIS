<?php

namespace App\Http\Requests\Transfers;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowTransferRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewTransfers') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
