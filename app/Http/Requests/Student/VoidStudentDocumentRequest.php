<?php

namespace App\Http\Requests\Student;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use App\Database\SchemaHelper;

class VoidStudentDocumentRequest extends FormRequest
{
    use RequiresStudentIdempotencyKey;

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

        return $this->user()?->can('update', $student) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
