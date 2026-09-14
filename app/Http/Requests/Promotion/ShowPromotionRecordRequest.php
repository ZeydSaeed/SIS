<?php

namespace App\Http\Requests\Promotion;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ShowPromotionRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewPromotion') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
