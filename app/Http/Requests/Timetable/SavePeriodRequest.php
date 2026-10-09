<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** «توقيت الحصص»: add / re-time one period of the school day. */
class SavePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePeriods', ScheduleRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'period_number' => ['required', 'integer', 'min:1', 'max:20'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'period_type' => ['required', 'integer', 'in:1,2'],
            // Presentation (optional): title, abbreviation, colour, where it shows / prints (bitmask 1·2·4·8·16).
            'name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'abbreviation' => ['sometimes', 'nullable', 'string', 'max:20'],
            'color_hue' => ['sometimes', 'nullable', 'integer', 'between:0,359'],
            'show_in' => ['sometimes', 'integer', 'between:0,31'],
            'print_in' => ['sometimes', 'integer', 'between:0,31'],
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
