<?php

namespace App\Http\Requests\Attendance;

final class RequestAttendanceReportExportRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'viewReports';
    }

    protected function fields(): array
    {
        return [
            'type' => ['required', 'string', 'in:students,student_detail,sections,subjects,teachers,daily,staff,lesson_types,periods,excuses,risk'],
            'format' => ['required', 'integer', 'in:1,2'],
            'filters' => ['nullable', 'array'],
        ];
    }
}
