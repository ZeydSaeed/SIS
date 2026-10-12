<?php

namespace App\Http\Requests\Attendance;

final class IssueNfcStationTokenRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'recordStaff';
    }

    protected function fields(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:100'],
        ];
    }
}
