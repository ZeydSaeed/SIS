<?php

namespace App\Http\Requests\Attendance;

final class SubmitAttendanceExcuseRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'submitExcuse';
    }

    protected function fields(): array
    {
        return [
            'enrollment_id' => ['required', 'integer', 'min:1'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'session_id' => ['nullable', 'integer', 'min:1'],
            'excuse_type' => ['required', 'integer', 'between:1,9'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'document_file_id' => ['nullable', 'integer', 'min:1'],
            'document' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'student_id' => ['prohibited'],
        ];
    }
}
