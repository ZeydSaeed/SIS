<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class ListJobPositionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewHr') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'integer', 'in:1,2'],
        ];
    }
}
