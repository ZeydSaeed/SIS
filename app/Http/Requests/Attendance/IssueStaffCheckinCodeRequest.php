<?php

namespace App\Http\Requests\Attendance;

final class IssueStaffCheckinCodeRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'recordStaff';
    }

    protected function fields(): array
    {
        return [];
    }
}
