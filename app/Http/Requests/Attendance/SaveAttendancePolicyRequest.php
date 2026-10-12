<?php

namespace App\Http\Requests\Attendance;

final class SaveAttendancePolicyRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'managePolicy';
    }

    protected function fields(): array
    {
        return [
            'timezone' => ['required', 'string', 'timezone:all'],
            'open_before_minutes' => ['required', 'integer', 'between:0,240'],
            'submit_deadline_minutes' => ['required', 'integer', 'between:0,1440'],
            'edit_window_days' => ['required', 'integer', 'between:0,30'],
            'late_grace_minutes' => ['required', 'integer', 'between:0,120'],
            'late_as_absent_minutes' => ['nullable', 'integer', 'between:1,240'],
            'require_complete' => ['required', 'boolean'],
            'default_mark' => ['required', 'integer', 'in:0,1'],
            'teacher_can_mark_excused' => ['required', 'boolean'],
            'absence_alert_count' => ['required', 'integer', 'between:1,100'],
            'absence_alert_window_days' => ['required', 'integer', 'between:1,180'],
            'absence_rate_alert_percent' => ['required', 'integer', 'between:1,100'],
            'late_alert_count' => ['required', 'integer', 'between:1,100'],
            'returned_alert_count' => ['required', 'integer', 'between:1,100'],
            'staff_day_start' => ['required', 'date_format:H:i'],
            'staff_late_grace_minutes' => ['required', 'integer', 'between:0,240'],
            'checkin_code_ttl_minutes' => ['required', 'integer', 'between:1,120'],
            'qr_enabled' => ['sometimes', 'boolean'],
            'nfc_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
