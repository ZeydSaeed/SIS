<?php

namespace App\Http\Requests\Timetable;

use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheduleId = (int) $this->route('schedule');
        $schedule = ScheduleRecord::query()->find($scheduleId);
        if ($schedule === null) {
            throw ScheduleNotFoundException::forId($scheduleId);
        }

        return $this->user()?->can('createException', $schedule) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'exception_date' => ['required', 'date'],
            'substitute_teacher_id' => ['nullable', 'integer', 'min:1'],
            'substitute_room_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'school_id' => ['prohibited'],
            'schedule_id' => ['prohibited'],
            'created_by' => ['prohibited'],
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
