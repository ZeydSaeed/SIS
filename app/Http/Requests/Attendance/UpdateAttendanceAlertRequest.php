<?php

namespace App\Http\Requests\Attendance;

final class UpdateAttendanceAlertRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'manageAlerts';
    }

    protected function fields(): array
    {
        return [
            'alert_status' => ['required', 'integer', 'in:2,3,4'],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:alert_status,4'],
        ];
    }
}
