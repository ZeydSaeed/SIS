<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

trait RequiresHrIdempotencyKey
{
    protected function prepareForValidation(): void
    {
        if (! $this->headers->has('X-Idempotency-Key') || trim((string) $this->header('X-Idempotency-Key')) === '') {
            abort(response()->json([
                'message' => 'X-Idempotency-Key header is required.',
                'error_code' => 'hr.idempotency_key_required',
            ], 422));
        }
    }
}
