<?php

namespace App\Application\Enrollment\Results;

use App\Application\Shared\Results\ApplicationResult;

final readonly class BulkEnrollStudentsResult extends ApplicationResult
{
    /**
     * @param  list<int>  $enrolledStudentIds
     * @param  list<array{student_id:int, error_code:string}>  $skipped
     */
    private function __construct(
        public array $enrolledStudentIds,
        public array $skipped,
    ) {
        parent::__construct(true);
    }

    /**
     * @param  list<int>  $enrolledStudentIds
     * @param  list<array{student_id:int, error_code:string}>  $skipped
     */
    public static function completed(array $enrolledStudentIds, array $skipped): self
    {
        return new self($enrolledStudentIds, $skipped);
    }
}
