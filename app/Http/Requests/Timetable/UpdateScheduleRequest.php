<?php

namespace App\Http\Requests\Timetable;

use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scheduleId = (int) $this->route('schedule');
        $schedule = ScheduleRecord::query()->find($scheduleId);
        if ($schedule === null) {
            throw ScheduleNotFoundException::forId($scheduleId);
        }

        return $this->user()?->can('updateSchedule', $schedule) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'section_id' => ['required', 'integer', 'min:1'],
            'day_of_week' => ['required', 'integer', 'min:1', 'max:7'],
            'period_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'teacher_id' => ['required', 'integer', 'min:1'],
            'room_id' => ['nullable', 'integer', 'min:1'],
            'school_id' => ['prohibited'],
            'lifecycle_status' => ['prohibited'],
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
