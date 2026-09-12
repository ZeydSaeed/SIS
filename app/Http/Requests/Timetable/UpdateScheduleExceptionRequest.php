<?php

namespace App\Http\Requests\Timetable;

use App\Domain\Timetable\Exceptions\ScheduleExceptionNotFoundException;
use App\Infrastructure\Persistence\Eloquent\ScheduleExceptionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exceptionId = (int) $this->route('exception');
        $exception = ScheduleExceptionRecord::query()->find($exceptionId);
        if ($exception === null) {
            throw ScheduleExceptionNotFoundException::forId($exceptionId);
        }

        return $this->user()?->can('updateException', $exception) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'schedule_id' => ['required', 'integer', 'min:1'],
            'exception_date' => ['required', 'date'],
            'substitute_teacher_id' => ['nullable', 'integer', 'min:1'],
            'substitute_room_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'school_id' => ['prohibited'],
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
