<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;

/** Link a teacher to the user account that signs in as them (email of an existing user); an empty email unlinks. */
final readonly class LinkTeacherUserAccountCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $teacherId,
        public ?string $email,
        public int $actorUserId,
        public string $idempotencyKey,
    ) {}
}
