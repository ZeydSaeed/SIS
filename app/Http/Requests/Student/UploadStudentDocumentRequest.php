<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

class UploadStudentDocumentRequest extends FormRequest
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
        $maxKb = (int) ceil(((int) config('sis.documents.max_bytes', 10 * 1024 * 1024)) / 1024);
        /** @var list<string> $mimes */
        $mimes = config('sis.documents.allowed_mimes', [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
        ]);

        return array_merge([
            'document_type' => ['required', 'integer', 'in:1,2,3,4,9,11,12,13,14,15,16,17,18,19'],
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimetypes:'.implode(',', $mimes)],
            'student_id' => ['prohibited'],
            'school_id' => ['prohibited'],
            'storage_key' => ['prohibited'],
            'file_hash' => ['prohibited'],
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
