<?php

namespace App\Http\Requests\Vocational;

use App\Infrastructure\Persistence\Eloquent\WorkshopRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateWorkshopRequest extends FormRequest
{
    use RequiresVocationalIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('manage', WorkshopRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1', 'max:32767'],
            'safety_capacity' => ['required', 'integer', 'min:1', 'max:32767'],
            'room_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
