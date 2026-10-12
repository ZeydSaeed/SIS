<?php

namespace App\Http\Requests\Attendance;

final class ReviewAttendanceSheetRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'reviewSheet';
    }

    protected function fields(): array
    {
        return [
            'action' => ['required', 'string', 'in:start_review,approve,return,reject'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:action,return,reject'],
        ];
    }
}
