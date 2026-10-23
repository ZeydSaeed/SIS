<?php

namespace App\Http\Requests\Results;

use App\Application\Results\Commands\ProcessYearResultsBatchCommand;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Validation\Rule;

/** Shared rules of the annual / GPA sheet actions: the server chooses the students, never the client. */
trait ProcessYearResultsBatchRules
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'target' => ['required', 'string', Rule::in([ProcessYearResultsBatchCommand::ANNUAL, ProcessYearResultsBatchCommand::GPA])],
            'enrollment_ids' => ['prohibited'],
            'enrollment_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
