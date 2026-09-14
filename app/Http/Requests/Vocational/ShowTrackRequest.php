<?php

namespace App\Http\Requests\Vocational;

use App\Infrastructure\Persistence\Eloquent\SpecializationRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', SpecializationRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
