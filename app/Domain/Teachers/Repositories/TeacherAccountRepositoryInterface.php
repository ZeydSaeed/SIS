<?php

namespace App\Domain\Teachers\Repositories;

/** The link between a teacher record and the user account that signs in as that teacher (teachers.teachers.user_id). */
interface TeacherAccountRepositoryInterface
{
    public function teacherWorksInSchool(int $teacherId, int $schoolId, int $academicYearId): bool;

    public function userIdByEmail(string $email): ?int;

    /** Another teacher already linked to this user, if any. */
    public function teacherLinkedToUser(int $userId, int $exceptTeacherId): ?int;

    public function currentUserId(int $teacherId): ?int;

    public function link(int $teacherId, ?int $userId): void;
}
