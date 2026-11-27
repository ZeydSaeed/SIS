<?php

namespace App\Application\Curriculum\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\UnitOfWork;
use App\Application\Curriculum\Results\CreateCurriculumWithTeachersResult;
use App\Application\Teachers\Commands\AssignCurriculumTeacherCommand;
use App\Application\Teachers\Commands\AssignCurriculumTeacherHandler;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Teachers\ValueObjects\TeachingRole;

/**
 * One transaction: the curriculum and its subjects, then each teacher (main teachers before assistants). Any refusal
 * — a teacher outside the school, a second main teacher for a part … — undoes everything and is returned.
 */
final class CreateCurriculumWithTeachersHandler implements CommandHandler
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly CreateCurriculumHandler $curricula,
        private readonly AssignCurriculumTeacherHandler $teachers,
        private readonly CurriculumRepositoryInterface $lines,
    ) {}

    public function handle(Command $command): CreateCurriculumWithTeachersResult
    {
        assert($command instanceof CreateCurriculumWithTeachersCommand);

        try {
            return $this->unitOfWork->transaction(function () use ($command): CreateCurriculumWithTeachersResult {
                $created = $this->curricula->handle($command->curriculum);
                if ($created->failed() || $created->curriculumId === null) {
                    throw new CurriculumCreationRefused($created->errors);
                }
                $lineBySubject = [];
                foreach ($this->lines->listLinks($command->curriculum->schoolId, $created->curriculumId) as $line) {
                    $lineBySubject[$line->subjectId] = $line->id;
                }

                $teachers = $command->teachers;
                usort($teachers, static fn (array $a, array $b): int => ($a['role'] === TeachingRole::Main ? 0 : 1) <=> ($b['role'] === TeachingRole::Main ? 0 : 1));
                foreach ($teachers as $index => $teacher) {
                    $lineId = $lineBySubject[$teacher['subject_id']] ?? null;
                    if ($lineId === null) {
                        throw new CurriculumCreationRefused(['curriculum.teacher_subject_not_in_curriculum']);
                    }
                    $assigned = $this->teachers->handle(new AssignCurriculumTeacherCommand(
                        schoolId: $command->curriculum->schoolId,
                        curriculumSubjectId: $lineId,
                        teacherId: $teacher['teacher_id'],
                        role: $teacher['role'],
                        idempotencyKey: $command->curriculum->idempotencyKey.':teacher:'.$index,
                        subjectType: $teacher['subject_type'],
                        sectionId: $teacher['section_id'] ?? null,
                    ));
                    if ($assigned->failed()) {
                        throw new CurriculumCreationRefused($assigned->errors);
                    }
                }

                return CreateCurriculumWithTeachersResult::success($created->curriculumId, $created->fromIdempotencyCache);
            });
        } catch (CurriculumCreationRefused $refused) {
            return CreateCurriculumWithTeachersResult::failure($refused->errors !== [] ? $refused->errors : ['curriculum.curriculum_create_failed']);
        }
    }
}
