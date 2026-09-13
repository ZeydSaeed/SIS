<?php

namespace App\Http\Requests\Vocational;

use App\Infrastructure\Persistence\Eloquent\WorkshopRecord;
use Illuminate\Foundation\Http\FormRequest;

class ListWorkshopsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', WorkshopRecord::class) ?? false;
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
