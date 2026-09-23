<?php

namespace App\Http\Requests\Enrollment;

use App\Domain\Student\ValueObjects\StudentStatus;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeEnrollmentStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', EnrollmentRecord::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('status')) {
            $this->merge([
                'status' => (int) $this->input('status'),
            ]);
        }

        if ($this->exists('enrollment_ids') && is_array($this->input('enrollment_ids'))) {
            $this->merge([
                'enrollment_ids' => array_values(array_map(
                    static fn ($id): int => (int) $id,
                    $this->input('enrollment_ids'),
                )),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_map(
            static fn (StudentStatus $status): int => $status->value,
            StudentStatus::cases(),
        );

        return [
            'enrollment_ids' => ['required', 'array', 'min:1', 'max:100'],
            'enrollment_ids.*' => ['required', 'integer', 'min:1'],
            // Unified "حالة الطالب" codes (not enrollment placement codes).
            'status' => ['present', 'integer', Rule::in($statuses)],
            'effective_to' => ['nullable', 'date'],
        ];
    }
}
