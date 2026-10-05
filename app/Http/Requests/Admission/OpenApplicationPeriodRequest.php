<?php

namespace App\Http\Requests\Admission;

use App\Security\Context\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class OpenApplicationPeriodRequest extends FormRequest
{
    use ValidatesApplicationPeriodAcademicYear;

    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmission') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            // The X-School-Id header already switched the context to an allowed school; the field must agree with it.
            'school_id' => ['required', 'integer', Rule::in($schoolId === null ? [] : [$schoolId])],
            'academic_year_id' => $this->academicYearIdRule(),
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_applications' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
