<?php

namespace App\Http\Requests\Results;

use App\Security\Validation\SecuritySensitiveFieldGuard;

/** Shared rules of the term-results sheet actions: the server chooses the students, never the client. */
trait ProcessTermResultsBatchRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'term_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'enrollment_ids' => ['prohibited'],
            'enrollment_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
