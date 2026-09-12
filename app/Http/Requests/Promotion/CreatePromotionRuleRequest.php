<?php

namespace App\Http\Requests\Promotion;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class CreatePromotionRuleRequest extends FormRequest
{
    use RequiresPromotionIdempotencyKey;

    public function authorize(): bool
    {
        return $this->user()?->can('managePromotion') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'from_grade_level_id' => ['required', 'integer', 'min:1'],
            'to_grade_level_id' => ['required', 'integer', 'min:1'],
            'min_gpa' => ['nullable', 'numeric', 'min:0'],
            'min_pass_subjects' => ['nullable', 'integer', 'min:0'],
            'max_failed_subjects' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
