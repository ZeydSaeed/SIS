<?php

namespace App\Http\Requests\Timetable;

use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheduleId = (int) $this->route('schedule');
        $schedule = ScheduleRecord::query()->find($scheduleId);
        if ($schedule === null) {
            throw ScheduleNotFoundException::forId($scheduleId);
        }

        return $this->user()?->can('cancelSchedule', $schedule) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'school_id' => ['prohibited'],
            'schedule_id' => ['prohibited'],
            'lifecycle_status' => ['prohibited'],
            'cancelled_by' => ['prohibited'],
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
