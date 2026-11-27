<?php

namespace App\Http\Requests\Admission;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** «حذف فترة التقديم» (safe delete) — same right as managing admission. */
final class DeleteApplicationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmission') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
