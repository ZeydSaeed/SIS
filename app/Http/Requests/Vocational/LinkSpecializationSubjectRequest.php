<?php

namespace App\Http\Requests\Vocational;

use App\Domain\Vocational\Exceptions\VocationalNotFoundException;
use App\Infrastructure\Persistence\Eloquent\SpecializationRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class LinkSpecializationSubjectRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', 'min:1'],
            'is_required' => ['sometimes', 'boolean'],
            'credit_hours' => ['nullable', 'integer', 'min:0', 'max:100'],
            'school_id' => ['prohibited'],
            'specialization_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
