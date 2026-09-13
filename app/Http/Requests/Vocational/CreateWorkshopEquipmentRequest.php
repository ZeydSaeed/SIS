<?php

namespace App\Http\Requests\Vocational;

use App\Infrastructure\Persistence\Eloquent\WorkshopRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateWorkshopEquipmentRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1', 'max:32767'],
            'school_id' => ['prohibited'],
            'workshop_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
