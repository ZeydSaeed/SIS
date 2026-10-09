<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\UpdateTeacherAppearanceResult;
use App\Domain\Shared\ValueObjects\DisplayAppearance;
use App\Domain\Teachers\Events\TeacherUpdated;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\Support\TeacherIdempotencyGuard;

final class UpdateTeacherAppearanceHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateTeacherAppearance';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateTeacherAppearanceResult
    {
        assert($command instanceof UpdateTeacherAppearanceCommand);
        $key = TeacherIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateTeacherAppearanceResult::fromIdempotency((int) $cached['teacher_id']);
        }

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId)) {
            return UpdateTeacherAppearanceResult::failure(['teachers.not_found']);
        }
        $appearance = DisplayAppearance::of($command->abbreviation, $command->colorHue);
        $error = $appearance->rejection();
        if ($error !== null) {
            return UpdateTeacherAppearanceResult::failure([$error]);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $appearance): void {
            $this->teachers->updateTeacher($command->teacherId, $appearance->toArray() + [
                'updated_at' => (new \DateTimeImmutable)->format('Y-m-d H:i:s'),
            ]);
            $this->outbox->stage(new TeacherUpdated($command->teacherId, $command->schoolId, new \DateTimeImmutable));
            $this->idempotency->store($key, self::COMMAND_NAME, ['teacher_id' => $command->teacherId]);
        });

        return UpdateTeacherAppearanceResult::success($command->teacherId);
    }
}
