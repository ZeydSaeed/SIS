<?php

namespace App\Http\Requests\Promotion;

use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RecordPromotionDecisionRequest extends FormRequest
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
            'enrollment_id' => ['required', 'integer', 'min:1'],
            'academic_year_id' => ['required', 'integer', 'min:1'],
            'to_grade_level_id' => ['required', 'integer', 'min:1'],
            'promotion_status' => ['required', 'integer', 'in:1,2,3'],
            'gpa_at_promotion' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'school_id' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
