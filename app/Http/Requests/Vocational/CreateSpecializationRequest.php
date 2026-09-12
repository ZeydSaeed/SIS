<?php

namespace App\Http\Requests\Vocational;

use App\Infrastructure\Persistence\Eloquent\SpecializationRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateSpecializationRequest extends FormRequest
{
    use RequiresVocationalIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manage', SpecializationRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
