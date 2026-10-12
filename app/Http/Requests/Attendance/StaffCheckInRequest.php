<?php

namespace App\Http\Requests\Attendance;

final class StaffCheckInRequest extends AttendanceWriteRequest
{
    protected function ability(): string
    {
        return 'staffCheckIn';
    }

    protected function fields(): array
    {
        return [
            // Rotating code (typed / QR) is 4–10 digits; an NFC station token is 24 hex characters.
            'code' => ['required', 'string', $this->input('via') === 'nfc' ? 'regex:/^\s*[0-9a-f]{24}\s*$/' : 'regex:/^\s*\d{4,10}\s*$/'],
            'via' => ['sometimes', 'string', 'in:code,qr,nfc'],
            'teacher_id' => ['prohibited'],
        ];
    }
}
