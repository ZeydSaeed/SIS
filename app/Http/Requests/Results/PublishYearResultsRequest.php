<?php

namespace App\Http\Requests\Results;

use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Publish the official annual results / year GPA of a class / section to students — results.publish. */
class PublishYearResultsRequest extends FormRequest
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
            'class_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'target' => ['required', 'string', Rule::in(['annual', 'gpa', 'transcript'])],
            'enrollment_ids' => ['prohibited'],
            'enrollment_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
