<?php

namespace App\Http\Requests\Vocational;

use App\Domain\Vocational\Exceptions\VocationalNotFoundException;
use App\Infrastructure\Persistence\Eloquent\SpecializationSubjectRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class DeactivateSpecializationSubjectRequest extends FormRequest
{
    use RequiresVocationalIdempotencyKey;

    public function authorize(): bool
    {
        $id = (int) $this->route('link');
        $record = SpecializationSubjectRecord::query()->find($id);
        if ($record === null) {
            throw VocationalNotFoundException::subjectLink($id);
        }

        return $this->user()?->can('manage', $record) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
