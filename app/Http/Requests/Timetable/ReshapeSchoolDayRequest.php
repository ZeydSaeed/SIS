<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** «الاستراحات وإعدادات الحصص»: one re-timing operation on the school day (same holders as «توقيت الحصص»). */
class ReshapeSchoolDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePeriods', ScheduleRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $operation = (string) $this->input('operation');

        return array_merge([
            'operation' => ['required', 'string', 'in:insert_break,move_break,resize,remove_break,retime,fixed_pattern'],
            'period_id' => [in_array($operation, ['move_break', 'resize', 'remove_break', 'retime'], true) ? 'required' : 'nullable', 'integer', 'min:1'],
            'after_period_id' => ['nullable', 'integer', 'min:1'],
            'minutes' => [in_array($operation, ['insert_break', 'resize', 'fixed_pattern'], true) ? 'required' : 'nullable', 'integer', 'between:1,180'],
            'start_time' => [in_array($operation, ['retime', 'fixed_pattern'], true) ? 'required' : 'nullable', 'date_format:H:i'],
            'end_time' => [$operation === 'retime' ? 'required' : 'nullable', 'date_format:H:i'],
            'cascade' => ['nullable', 'boolean'],
            'name' => ['nullable', 'string', 'max:60'],
            'abbreviation' => ['nullable', 'string', 'max:20'],
            'color_hue' => ['nullable', 'integer', 'between:0,359'],
            'show_in' => ['nullable', 'integer', 'between:0,31'],
            'print_in' => ['nullable', 'integer', 'between:0,31'],
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
