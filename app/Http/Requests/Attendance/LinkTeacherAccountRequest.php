<?php

namespace App\Http\Requests\Attendance;

final class LinkTeacherAccountRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'linkTeacherAccount';
    }

    protected function fields(): array
    {
        return [
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
