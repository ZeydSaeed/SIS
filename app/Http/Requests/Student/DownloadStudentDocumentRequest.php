<?php

namespace App\Http\Requests\Student;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DownloadStudentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $documentId = (int) $this->route('document');
        $studentId = DB::table(SchemaHelper::qualified('students', 'student_documents'))
            ->where('id', $documentId)
            ->value('student_id');
        if ($studentId === null) {
            return false;
        }

        $student = StudentRecord::query()->find((int) $studentId);
        if ($student === null) {
            return false;
        }

        return $this->user()?->can('view', $student) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SecuritySensitiveFieldGuard::prohibitedRules();
    }
}
