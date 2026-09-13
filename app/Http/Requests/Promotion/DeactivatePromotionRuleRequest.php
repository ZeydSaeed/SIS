<?php

namespace App\Http\Requests\Promotion;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class DeactivatePromotionRuleRequest extends FormRequest
{
    use RequiresPromotionIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('managePromotion') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'is_active' => ['prohibited'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
