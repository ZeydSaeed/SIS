<?php

namespace App\Http\Requests\Attendance;

final class DetectAttendanceAlertsRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'manageAlerts';
    }

    protected function fields(): array
    {
        return [];
    }
}
