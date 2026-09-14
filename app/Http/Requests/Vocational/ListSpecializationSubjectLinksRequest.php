<?php

namespace App\Http\Requests\Vocational;

use App\Infrastructure\Persistence\Eloquent\SpecializationRecord;
use Illuminate\Foundation\Http\FormRequest;

class ListSpecializationSubjectLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', SpecializationRecord::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'link_status' => ['sometimes', 'nullable', 'integer', 'in:1,2'],
        ];
    }
}
