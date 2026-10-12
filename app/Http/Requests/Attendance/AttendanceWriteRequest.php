<?php

namespace App\Http\Requests\Attendance;

use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Validation\SecuritySensitiveFieldGuard;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base of the attendance workflow writes: authorization through AttendancePolicy (permission + school context),
 * the shared prohibited-field rules (no client-chosen school / status / approver), and the scope fields the server
 * derives itself (teacher, student, recorder) are prohibited too.
 */
abstract class AttendanceWriteRequest extends FormRequest
{
    abstract protected function ability(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function fields(): array;

    public function authorize(): bool
    {
        return $this->user()?->can($this->ability(), AttendanceSessionRecord::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            SecuritySensitiveFieldGuard::prohibitedRules(),
            ['recorded_by' => ['prohibited'], 'reviewed_by' => ['prohibited'], 'submitted_by' => ['prohibited']],
            $this->fields(),
        );
    }

    /** Client retry key, namespaced per user so one user's key can never replay another user's result. */
    public function idempotencyKey(string $scope): string
    {
        $header = $this->header('X-Idempotency-Key');
        $key = is_string($header) && trim($header) !== '' ? substr(trim($header), 0, 120) : bin2hex(random_bytes(16));

        return 'u'.$this->user()?->getAuthIdentifier().':'.$scope.':'.$key;
    }
}
