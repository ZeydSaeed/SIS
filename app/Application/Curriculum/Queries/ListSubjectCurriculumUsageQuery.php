<?php

namespace App\Application\Curriculum\Queries;

/** Which of the school's curricula use each subject (the «حذف» warning on «المواد»). */
final readonly class ListSubjectCurriculumUsageQuery
{
    public function __construct(
        public int $schoolId,
    ) {}
}
