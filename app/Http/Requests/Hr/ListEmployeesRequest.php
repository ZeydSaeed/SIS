<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;

class ListEmployeesRequest extends FormRequest
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
            'academic_year_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'integer', 'in:1,2'],
        ];
    }
}
