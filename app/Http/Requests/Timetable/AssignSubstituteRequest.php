<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** «بديل ليوم» from the timetable page: a substitute teacher (and / or room) for one lesson on one date. */
class AssignSubstituteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createException', ScheduleRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'exception_date' => ['required', 'date_format:Y-m-d'],
            'substitute_teacher_id' => ['nullable', 'integer', 'min:1', 'required_without:substitute_room_id'],
            'substitute_room_id' => ['nullable', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (trim((string) $this->header('X-Idempotency-Key')) === '') {
                $validator->errors()->add('X-Idempotency-Key', 'The X-Idempotency-Key header is required.');
            }
        });
    }
}
