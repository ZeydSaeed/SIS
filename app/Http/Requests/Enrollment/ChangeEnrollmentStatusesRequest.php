<?php

namespace App\Http\Requests\Enrollment;

use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeEnrollmentStatusesRequest extends FormRequest
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
            'status' => ['required', 'integer', Rule::in(EnrollmentStatus::all())],
            'effective_to' => ['nullable', 'date'],
        ];
    }
}
