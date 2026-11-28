<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\SetTeachersAccessLockResult;
use App\Domain\Teachers\Events\TeacherUpdated;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

/** Bulk «قفل» / «فتح القفل» — all-or-nothing: every teacher must belong to the school. */
final class SetTeachersAccessLockHandler implements CommandHandler
{
    private const COMMAND_NAME = 'SetTeachersAccessLock';

    public const MAX_TEACHERS = 200;

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SetTeachersAccessLockResult
    {
        assert($command instanceof SetTeachersAccessLockCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return SetTeachersAccessLockResult::success(array_map('intval', $cached['teacher_ids'] ?? []), true);
        }

        $ids = array_values(array_unique(array_map('intval', $command->teacherIds)));
        if ($ids === [] || count($ids) > self::MAX_TEACHERS) {
            return SetTeachersAccessLockResult::failure('teachers.selection_invalid');
        }
        foreach ($ids as $teacherId) {
            if (! $this->teachers->belongsToSchool($teacherId, $command->schoolId)) {
                return SetTeachersAccessLockResult::failure('teachers.not_found');
            }
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $ids, $key, $at): void {
            foreach ($ids as $teacherId) {
                $this->teachers->updateTeacher($teacherId, ['access_locked' => $command->locked, 'updated_at' => $at]);
                $this->outbox->stage(new TeacherUpdated($teacherId, $command->schoolId, new \DateTimeImmutable));
            }
            $this->idempotency->store($key, self::COMMAND_NAME, ['teacher_ids' => $ids]);
        });

        return SetTeachersAccessLockResult::success($ids);
    }
}
