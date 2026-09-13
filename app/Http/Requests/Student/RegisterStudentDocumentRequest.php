<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class RegisterStudentDocumentRequest extends FormRequest
{
    use RequiresStudentIdempotencyKey;

    public function authorize(): bool
    {
        $student = StudentRecord::query()->find($this->route('student'));
        if ($student === null) {
            return false;
        }

        return $this->user()?->can('update', $student) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'document_type' => ['required', 'integer', 'in:1,2,3,4,9'],
            'storage_key' => ['required', 'string', 'max:500'],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer', 'min:0'],
            'file_hash' => ['required', 'string', 'size:64', 'regex:/^[a-fA-F0-9]{64}$/'],
            'student_id' => ['prohibited'],
            'school_id' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
