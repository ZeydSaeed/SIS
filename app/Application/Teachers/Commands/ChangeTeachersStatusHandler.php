<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\ChangeTeachersStatusResult;
use App\Domain\Teachers\Events\TeacherDeactivated;
use App\Domain\Teachers\Events\TeacherReactivated;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;
use App\Domain\Teachers\ValueObjects\TeacherStatus;

/** Bulk نشط / غير نشط — all-or-nothing: every teacher must belong to the school. */
final class ChangeTeachersStatusHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ChangeTeachersStatus';

    public const MAX_TEACHERS = 200;

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ChangeTeachersStatusResult
    {
        assert($command instanceof ChangeTeachersStatusCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ChangeTeachersStatusResult::success(array_map('intval', $cached['teacher_ids'] ?? []), true);
        }

        $ids = array_values(array_unique(array_map('intval', $command->teacherIds)));
        if ($ids === [] || count($ids) > self::MAX_TEACHERS) {
            return ChangeTeachersStatusResult::failure('teachers.selection_invalid');
        }

        if (! in_array($command->status, [TeacherStatus::Active, TeacherStatus::Inactive], true)) {
            return ChangeTeachersStatusResult::failure('teachers.status_invalid');
        }

        foreach ($ids as $teacherId) {
            if (! $this->teachers->belongsToSchool($teacherId, $command->schoolId)) {
                return ChangeTeachersStatusResult::failure('teachers.not_found');
            }
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $this->unitOfWork->transaction(function () use ($command, $ids, $key, $at): void {
            foreach ($ids as $teacherId) {
                $this->teachers->setStatus($teacherId, $command->status, $at);
                $this->outbox->stage($command->status === TeacherStatus::Active
                    ? new TeacherReactivated($teacherId, $command->schoolId, new \DateTimeImmutable)
                    : new TeacherDeactivated($teacherId, $command->schoolId, new \DateTimeImmutable));
            }
            $this->idempotency->store($key, self::COMMAND_NAME, ['teacher_ids' => $ids]);
        });

        $lessons = $command->status === TeacherStatus::Inactive ? $this->teachers->activeLessonCount($ids, $command->schoolId) : 0;

        return ChangeTeachersStatusResult::success($ids, false, $lessons > 0 ? ['teachers.deactivated_with_lessons?count='.$lessons] : []);
    }
}
