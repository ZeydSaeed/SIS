<?php

namespace App\Http\Requests\Intelligence;

use Illuminate\Foundation\Http\FormRequest;

class RejectRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'chose_instead' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
