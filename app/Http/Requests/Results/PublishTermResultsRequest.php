<?php

namespace App\Http\Requests\Results;

use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Publish the official term results of one subject of a class / section to students — results.publish. */
class PublishTermResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish', TermResultRecord::class) ?? false;
    }

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
