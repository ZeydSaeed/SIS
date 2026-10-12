<?php

namespace App\Http\Requests\Attendance;

final class BulkApproveAttendanceSheetsRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'reviewSheet';
    }

    protected function fields(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.session_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.version' => ['required', 'integer', 'min:1'],
        ];
    }
}
