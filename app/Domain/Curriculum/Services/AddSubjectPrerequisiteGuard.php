<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;

/**
 * Add-prerequisite preconditions — keeps Application handler within ARCH-103.
 */
final class AddSubjectPrerequisiteGuard
{
    public function __construct(
        private readonly PrerequisiteRepositoryInterface $prerequisites,
    ) {}

    public function rejectionCode(int $subjectId, int $prerequisiteSubjectId): ?string
    {
        if ($subjectId < 1 || $prerequisiteSubjectId < 1) {
            return 'curriculum.prerequisite_invalid';
        }
        if ($subjectId === $prerequisiteSubjectId) {
            return 'curriculum.prerequisite_self';
        }
        if (! $this->prerequisites->subjectExists($subjectId)) {
            return 'curriculum.subject_not_found';
        }
        if (! $this->prerequisites->subjectExists($prerequisiteSubjectId)) {
            return 'curriculum.prerequisite_subject_not_found';
        }
        if ($this->wouldCreateCycle($subjectId, $prerequisiteSubjectId)) {
            return 'curriculum.prerequisite_cycle';
        }

        return null;
    }

    private function wouldCreateCycle(int $subjectId, int $prerequisiteSubjectId): bool
    {
        $queue = [$prerequisiteSubjectId];
        $seen = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if ($current === $subjectId) {
                return true;
            }
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($this->prerequisites->activePrerequisiteSubjectIds($current) as $next) {
                $queue[] = $next;
            }
        }

        return false;
    }
}
