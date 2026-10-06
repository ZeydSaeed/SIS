<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** «توزيع تلقائي» for the sections on screen (one, or all under the current filters). */
class AutoPlaceSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createSchedule', ScheduleRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'section_ids' => ['required', 'array', 'min:1', 'max:500'],
            'section_ids.*' => ['integer', 'min:1', 'distinct'],
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
