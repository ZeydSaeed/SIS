<?php

namespace App\Http\Requests\Promotion;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class ListPromotionRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewPromotion') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'active_only' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
