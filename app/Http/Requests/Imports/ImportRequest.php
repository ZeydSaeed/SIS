<?php

namespace App\Http\Requests\Imports;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * «استيراد Excel» writes: upload (file + optional year) or commit / cancel a batch. Who may import which kind is
 * decided per kind in the controller (the owning page's ability); every write carries an X-Idempotency-Key.
 */
class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $fields = $this->route()?->getName() === 'imports.store' ? [
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,csv,txt'],
            'academic_year_id' => ['nullable', 'integer', 'min:1'],
        ] : [];

        return array_merge($fields, ['school_id' => ['prohibited']], SecuritySensitiveFieldGuard::prohibitedRules());
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
