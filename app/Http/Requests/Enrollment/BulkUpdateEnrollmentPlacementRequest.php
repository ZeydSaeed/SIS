<?php

namespace App\Http\Requests\Enrollment;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class BulkUpdateEnrollmentPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EnrollmentRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'enrollment_ids.*' => ['required', 'integer', 'min:1'],
            'class_id' => ['sometimes', 'integer', 'min:1'],
            'section_id' => ['sometimes', 'integer', 'min:1'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'specialization_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'gender' => ['sometimes', 'integer', 'in:1,2'],
            'allow_inactive' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ! $this->exists('class_id')
                && ! $this->exists('section_id')
                && ! $this->exists('branch_id')
                && ! $this->exists('department_id')
                && ! $this->exists('specialization_id')
                && ! $this->exists('gender')
            ) {
                $validator->errors()->add(
                    'enrollment_ids',
                    'At least one placement field must be provided.',
                );
            }
        });
    }
}
