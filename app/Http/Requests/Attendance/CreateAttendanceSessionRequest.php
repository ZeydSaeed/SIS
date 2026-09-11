<?php

namespace App\Http\Requests\Attendance;

use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreateAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createSession', AttendanceSessionRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'session_date' => ['required', 'date'],
            'teacher_id' => ['required', 'integer', 'min:1'],
            'period_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['prohibited'],
            'attendance_date' => ['prohibited'],
            'recorded_by' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
