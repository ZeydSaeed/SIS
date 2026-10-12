<?php

namespace App\Http\Requests\Attendance;

final class OpenLessonAttendanceRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'submitSheet';
    }

    protected function fields(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date_format:Y-m-d'],
            'teacher_id' => ['prohibited'],
        ];
    }
}
