<?php

namespace App\Http\Requests\Vocational;

use App\Infrastructure\Persistence\Eloquent\WorkshopRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ReactivateWorkshopRequest extends FormRequest
{
    use RequiresVocationalIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manage', WorkshopRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
