<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** API reads of the effective / student timetable (Attendance, portals). */
class ReadEffectiveTimetableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', ScheduleRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'section_id' => ['nullable', 'integer', 'min:1'],
            'teacher_id' => ['nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
