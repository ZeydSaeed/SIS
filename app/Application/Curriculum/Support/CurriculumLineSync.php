<?php

namespace App\Application\Curriculum\Support;

use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\SafeDeleter;
use App\Application\Curriculum\Commands\CurriculumCreationRefused;
use App\Domain\Curriculum\Events\CurriculumSubjectLinked;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;

/**
 * «تعديل منهج»: the curriculum's subject lines become exactly the chosen subjects — a removed subject's line goes
 * with its teachers (safe delete: refused while grades … use it), a returned one is reactivated, a new one is linked
 * with its hours (نظري / عملي from the subject's types). Runs inside the caller's transaction; a refusal throws.
 */
final class CurriculumLineSync
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly SafeDeleter $deleter,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @param  list<int>  $subjectIds
     * @return array<int, int> subject id → line id
     */
    public function sync(int $schoolId, int $curriculumId, array $subjectIds): array
    {
        $wanted = array_values(array_unique(array_map('intval', $subjectIds)));
        $existing = [];
        foreach ($this->curricula->listLinks($schoolId, $curriculumId) as $line) {
            if (! in_array($line->subjectId, $wanted, true)) {
                if (($this->deleter->delete('curriculum_subject', $schoolId, $line->id)['status'] ?? '') !== 'deleted') {
                    throw new CurriculumCreationRefused(['curriculum.subject_line_in_use']);
                }

                continue;
            }
            $existing[$line->subjectId] = $line;
        }

        $lineBySubject = [];
        $now = (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM);
        foreach ($wanted as $order => $subjectId) {
            $line = $existing[$subjectId] ?? null;
            if ($line !== null) {
                if ($line->status !== 1) {
                    $this->curricula->reactivateLink($schoolId, $line->id);
                }
                $lineBySubject[$subjectId] = $line->id;

                continue;
            }
            $lineBySubject[$subjectId] = $this->link($schoolId, $curriculumId, $subjectId, $order, $now);
        }

        return $lineBySubject;
    }

    private function link(int $schoolId, int $curriculumId, int $subjectId, int $order, string $now): int
    {
        $subject = $this->subjects->findActive($subjectId);
        if ($subject === null) {
            throw new CurriculumCreationRefused(['curriculum.subject_not_found']);
        }
        // Theory = its «نظري» hours, practical = its «عملي» hours (when the subject's types carry them).
        $lineId = $this->curricula->linkSubject(
            $schoolId,
            $curriculumId,
            $subjectId,
            $subject->creditHours,
            true,
            $order,
            $now,
            $subject->subjectTypeHours[4] ?? null,
            $subject->subjectTypeHours[3] ?? null,
        );
        $this->outbox->stage(new CurriculumSubjectLinked($lineId, $curriculumId, $subjectId, new \DateTimeImmutable));

        return $lineId;
    }
}
