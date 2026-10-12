<?php

namespace App\Http\Requests\Attendance;

final class RecordStaffAttendanceRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'recordStaff';
    }

    protected function fields(): array
    {
        return [
            'teacher_id' => ['required', 'integer', 'min:1'],
            'work_date' => ['required', 'date_format:Y-m-d'],
            'attendance_status' => ['required', 'integer', 'between:1,7'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after_or_equal:check_in'],
            'minutes_late' => ['nullable', 'integer', 'between:0,1440'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'version' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
