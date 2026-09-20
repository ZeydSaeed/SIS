<?php

namespace App\Http\Requests\Student;

use App\Domain\Student\ValueObjects\StudentStatus;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ChangeStudentStatusesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAny', StudentRecord::class) ?? false;
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
            'student_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_ids.*' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'integer', Rule::in($statuses)],
        ];
    }
}
