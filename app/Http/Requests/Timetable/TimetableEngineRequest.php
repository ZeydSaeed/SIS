<?php

namespace App\Http\Requests\Timetable;

use App\Infrastructure\Persistence\Eloquent\ScheduleRecord;
use App\Security\Policies\TimetablePolicy;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Base of the timetable engine's web writes: the policy ability decides who may (server-side, school access
 * included), `school_id` can never be sent (it comes from SchoolContext), and every write carries an
 * X-Idempotency-Key so a retried click never applies twice.
 */
abstract class TimetableEngineRequest extends FormRequest
{
    /** The {@see TimetablePolicy} ability this write needs. */
    abstract protected function ability(): string;

    /** @return array<string, mixed> */
    abstract protected function fieldRules(): array;

    public function authorize(): bool
    {
        return $this->user()?->can($this->ability(), ScheduleRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge($this->fieldRules(), ['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! is_string($this->header('X-Idempotency-Key')) || trim((string) $this->header('X-Idempotency-Key')) === '') {
                $validator->errors()->add('X-Idempotency-Key', 'The X-Idempotency-Key header is required.');
            }
        });
    }

    public function idempotencyKey(): string
    {
        return trim((string) $this->header('X-Idempotency-Key'));
    }

    protected function yearRule(): array
    {
        return ['required', 'integer', 'min:1'];
    }
}
