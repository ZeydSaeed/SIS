<?php

namespace App\Http\Requests\Enrollment;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class DeactivateEnrollmentSubjectRequest extends FormRequest
{
    use RequiresEnrollmentIdempotencyKey;

    public function authorize(): bool
    {
        $linkId = (int) $this->route('link');
        $enrollmentId = DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))
            ->where('id', $linkId)
            ->value('enrollment_id');

        if ($enrollmentId === null) {
            // Deny class-level update probes; missing links fall through as 403/404 isolation.
            return false;
        }

        $enrollment = EnrollmentRecord::query()->find((int) $enrollmentId);
        if ($enrollment === null) {
            return false;
        }

        return $this->user()?->can('update', $enrollment) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge([
            'status' => ['prohibited'],
        ], SecuritySensitiveFieldGuard::prohibitedRules());
    }
}
