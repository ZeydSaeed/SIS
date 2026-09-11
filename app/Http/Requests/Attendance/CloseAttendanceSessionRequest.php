<?php

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CloseAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sessionId = (int) $this->route('session');
        $session = AttendanceSessionRecord::query()->find($sessionId);
        if ($session === null) {
            throw SessionNotFoundException::forId($sessionId);
        }

        return $this->user()?->can('closeSession', $session) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'status' => ['prohibited'],
            'attendance_date' => ['prohibited'],
            'recorded_by' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
