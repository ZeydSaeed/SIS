<?php

namespace App\Http\Requests\Attendance;

final class OpenExceptionalAttendanceRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'reviewSheet';
    }

    protected function fields(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'section_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'teacher_id' => ['required', 'integer', 'min:1'],
            'period_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
