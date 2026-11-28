<?php

namespace App\Application\Curriculum\Support;

use App\Application\Curriculum\Commands\CurriculumCreationRefused;
use App\Application\Teachers\Commands\AssignCurriculumTeacherCommand;
use App\Application\Teachers\Commands\AssignCurriculumTeacherHandler;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\ValueObjects\TeachingRole;

/**
 * «تعديل منهج»: each subject's teachers become exactly the chosen ones. A subject whose teachers did not change is left
 * as it is; otherwise its active assignments end (history kept) and the chosen ones are assigned again (main teachers
 * first), each checked like a single «إسناد مدرس». Runs inside the caller's transaction; a refusal throws.
 */
final class CurriculumTeacherSync
{
    public function __construct(
        private readonly TeacherRepositoryInterface $teachers,
        private readonly AssignCurriculumTeacherHandler $assign,
    ) {}

    /** The curriculum moved (another year / place): every active assignment of its lines ends. */
    public function endAll(int $schoolId, array $lineIds): void
    {
        foreach ($lineIds as $lineId) {
            $this->endLine($schoolId, (int) $lineId);
        }
    }

    /**
     * @param  array<int, int>  $lineBySubject  subject id → line id
     * @param  list<array{subject_id: int, subject_type: ?int, teacher_id: int, role: int, section_id: ?int}>  $rows
     */
    public function sync(int $schoolId, array $lineBySubject, array $rows, string $keyPrefix): void
    {
        $bySubject = [];
        foreach ($rows as $row) {
            if (! isset($lineBySubject[$row['subject_id']])) {
                throw new CurriculumCreationRefused(['curriculum.teacher_subject_not_in_curriculum']);
            }
            $bySubject[$row['subject_id']][] = $row;
        }

        foreach ($lineBySubject as $subjectId => $lineId) {
            $wanted = $bySubject[$subjectId] ?? [];
            $current = $this->teachers->activeLineAssignments($schoolId, $lineId);
            if (self::keys($wanted) === self::keys($current)) {
                continue;
            }
            $this->endLine($schoolId, $lineId);
            usort($wanted, static fn (array $a, array $b): int => ($a['role'] === TeachingRole::Main ? 0 : 1) <=> ($b['role'] === TeachingRole::Main ? 0 : 1));
            foreach ($wanted as $index => $row) {
                $result = $this->assign->handle(new AssignCurriculumTeacherCommand(
                    schoolId: $schoolId,
                    curriculumSubjectId: $lineId,
                    teacherId: $row['teacher_id'],
                    role: $row['role'],
                    idempotencyKey: $keyPrefix.':line:'.$lineId.':'.$index,
                    subjectType: $row['subject_type'],
                    sectionId: $row['section_id'],
                ));
                if ($result->failed()) {
                    throw new CurriculumCreationRefused($result->errors);
                }
            }
        }
    }

    private function endLine(int $schoolId, int $lineId): void
    {
        $now = new \DateTimeImmutable;
        foreach ($this->teachers->activeLineAssignments($schoolId, $lineId) as $row) {
            $this->teachers->endTeachingAssignment($schoolId, $row['id'], $now->format('Y-m-d'), $now->format('Y-m-d H:i:s'));
        }
    }

    /**
     * A comparable set of «part | section | teacher | role».
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private static function keys(array $rows): array
    {
        $keys = array_map(static function (array $row): string {
            $role = (int) ($row['role'] ?? 0);

            return ($row['subject_type'] ?? '-').'|'.($row['section_id'] ?? '-').'|'.$row['teacher_id'].'|'.$role;
        }, $rows);
        sort($keys);

        return array_values(array_unique($keys));
    }
}
