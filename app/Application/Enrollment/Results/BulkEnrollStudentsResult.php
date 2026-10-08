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
        array $warnings = [],
    ) {
        parent::__construct(true, [], $warnings);
    }

    /**
     * @param  list<int>  $enrolledStudentIds
     * @param  list<array{student_id:int, error_code:string}>  $skipped
     */
    public static function completed(array $enrolledStudentIds, array $skipped, array $warnings = []): self
    {
        return new self($enrolledStudentIds, $skipped, $warnings);
    }
}
