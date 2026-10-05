<?php

namespace App\Http\Requests\Admission;

use Illuminate\Foundation\Http\FormRequest;

final class OpenApplicationPeriodRequest extends FormRequest
{
    use ValidatesApplicationDirectorate;
    use ValidatesApplicationPeriodAcademicYear {
        messages as academicYearMessages;
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manageAdmission') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Periods belong to the academic year and are shared by every school.
            'school_id' => ['prohibited'],
            'academic_year_id' => $this->academicYearIdRule(),
            // The period belongs to the year and a directorate; only its schools apply in it.
            'directorate_id' => $this->applicationDirectorateRule(),
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_applications' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->academicYearMessages(), $this->applicationDirectorateMessages());
    }
}
