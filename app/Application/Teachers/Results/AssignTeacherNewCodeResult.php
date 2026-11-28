<?php

namespace App\Application\Teachers\Results;

use App\Application\Shared\Results\ApplicationResult;

/** «تعيين رقم جديد»: the teacher and the code now set (or why not). */
final readonly class AssignTeacherNewCodeResult extends ApplicationResult
{
    /** @param  list<string>  $errors */
    private function __construct(
        bool $success,
        public ?int $teacherId = null,
        public ?string $employeeCode = null,
        array $errors = [],
        bool $fromIdempotencyCache = false,
    ) {
        parent::__construct($success, $errors, [], $fromIdempotencyCache);
    }

    public static function fromChange(ChangeTeacherEmployeeCodeResult $change): self
    {
        return $change->failed()
            ? new self(false, null, null, $change->errors)
            : new self(true, $change->teacherId, $change->employeeCode, [], $change->fromIdempotencyCache);
    }

    /** @param  list<string>  $errors */
    public static function failure(array $errors): self
    {
        return new self(false, null, null, $errors);
    }
}
