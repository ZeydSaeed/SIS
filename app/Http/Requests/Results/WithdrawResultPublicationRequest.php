<?php

namespace App\Http\Requests\Results;

use App\Infrastructure\Persistence\Eloquent\TermResultRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/** Withdraw a published result set with a reason — results.publish. */
class WithdrawResultPublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish', TermResultRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
