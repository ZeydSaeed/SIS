<?php

namespace App\Http\Requests\Attendance;

final class SaveAttendanceSheetRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'submitSheet';
    }

    protected function fields(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'marks' => ['present', 'array', 'max:500'],
            'marks.*.enrollment_id' => ['required', 'integer', 'min:1', 'distinct'],
            'marks.*.status' => ['required', 'integer', 'between:0,5'],
            'marks.*.minutes_late' => ['nullable', 'integer', 'between:0,600'],
            'marks.*.note' => ['nullable', 'string', 'max:500'],
            'marks.*.student_id' => ['prohibited'],
            'confirm' => ['sometimes', 'accepted'],
        ];
    }
}
