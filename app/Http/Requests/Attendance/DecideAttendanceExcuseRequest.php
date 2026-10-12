<?php

namespace App\Http\Requests\Attendance;

final class DecideAttendanceExcuseRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'reviewExcuse';
    }

    protected function fields(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject'],
            'version' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ];
    }
}
