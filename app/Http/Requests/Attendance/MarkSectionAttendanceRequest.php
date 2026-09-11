<?php

namespace App\Http\Requests\Attendance;

use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MarkSectionAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sessionId = (int) $this->route('session');
        $session = AttendanceSessionRecord::query()->find($sessionId);
        if ($session === null) {
            throw SessionNotFoundException::forId($sessionId);
        }

        return $this->user()?->can('mark', $session) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.student_id' => ['required', 'integer', 'min:1', 'distinct'],
            'records.*.enrollment_id' => ['required', 'integer', 'min:1'],
            'records.*.status' => ['required', 'integer', 'in:1,2,3'],
            'records.*.notes' => ['nullable', 'string', 'max:2000'],
            'attendance_date' => ['prohibited'],
            'recorded_by' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! is_string($this->header('X-Idempotency-Key')) || trim((string) $this->header('X-Idempotency-Key')) === '') {
                $validator->errors()->add('X-Idempotency-Key', 'The X-Idempotency-Key header is required.');
            }
        });
    }
}
