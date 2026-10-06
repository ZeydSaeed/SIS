<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** «زحف المادة»: direction 1 = one period later, -1 = one period earlier. */
class ShiftScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSchedule', ScheduleRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'direction' => ['required', 'integer', 'in:-1,1'],
            'school_id' => ['prohibited'],
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
