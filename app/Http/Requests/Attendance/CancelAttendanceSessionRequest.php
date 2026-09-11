<?php

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sessionId = (int) $this->route('session');
        $session = AttendanceSessionRecord::query()->find($sessionId);
        if ($session === null) {
            throw SessionNotFoundException::forId($sessionId);
        }

        return $this->user()?->can('cancelSession', $session) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
            'status' => ['prohibited'],
            'school_id' => ['prohibited'],
            'session_id' => ['prohibited'],
            'academic_year_id' => ['prohibited'],
            'recorded_by' => ['prohibited'],
            'cancelled_by' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! is_string($this->header('X-Idempotency-Key')) || trim((string) $this->header('X-Idempotency-Key')) === '') {
                $validator->errors()->add('X-Idempotency-Key', 'The X-Idempotency-Key header is required.');
            }

            $reason = $this->input('reason');
            if (is_string($reason) && trim($reason) === '') {
                $validator->errors()->add('reason', 'The reason field is required and must be non-empty.');
            }
        });
    }
}
