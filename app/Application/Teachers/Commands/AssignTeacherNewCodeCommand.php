<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/** «تعيين رقم جديد»: the application makes the teacher a new «الرقم التعريفي» (TeacherCodeGenerator). */
final readonly class AssignTeacherNewCodeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $teacherId,
        public ?string $idempotencyKey,
    ) {}
}
