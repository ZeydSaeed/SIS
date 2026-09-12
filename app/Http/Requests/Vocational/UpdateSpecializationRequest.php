<?php

namespace App\Http\Requests\Vocational;

use App\Domain\Vocational\Exceptions\VocationalNotFoundException;
use App\Infrastructure\Persistence\Eloquent\SpecializationRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSpecializationRequest extends FormRequest
{
    use RequiresVocationalIdempotencyKey;

    public function authorize(): bool
    {
        $id = (int) $this->route('specialization');
        $record = SpecializationRecord::query()->find($id);
        if ($record === null) {
            throw VocationalNotFoundException::specialization($id);
        }

        return $this->user()?->can('manage', $record) ?? false;
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
