<?php

namespace App\Http\Requests\Bank;

use Illuminate\Validation\Validator;

trait RequiresBankIdempotencyKey
{
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
}
