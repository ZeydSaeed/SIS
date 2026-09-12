<?php

namespace App\Application\Teachers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Teachers\Results\UnlinkTeacherSubjectResult;
use App\Domain\Teachers\Events\TeacherSubjectUnlinked;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;

final class UnlinkTeacherSubjectHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TeacherRepositoryInterface $teachers,
        private readonly OutboxRepository $outbox,
    ) {}

    public function handle(Command $command): UnlinkTeacherSubjectResult
    {
        assert($command instanceof UnlinkTeacherSubjectCommand);

        if (! $this->teachers->belongsToSchool($command->teacherId, $command->schoolId, $command->academicYearId)) {
            return UnlinkTeacherSubjectResult::failure(['teachers.not_in_school_year']);
        }

        $wasPresent = false;
        $this->unitOfWork->transaction(function () use ($command, &$wasPresent): void {
            $wasPresent = $this->teachers->unlinkSubject(
                $command->teacherId,
                $command->subjectId,
                $command->academicYearId,
                $command->schoolId,
            );
            $this->outbox->stage(new TeacherSubjectUnlinked(
                $command->teacherId,
                $command->subjectId,
                $command->schoolId,
                $command->academicYearId,
                $wasPresent,
                new \DateTimeImmutable,
            ));
        });

        return UnlinkTeacherSubjectResult::success($wasPresent);
    }
}
