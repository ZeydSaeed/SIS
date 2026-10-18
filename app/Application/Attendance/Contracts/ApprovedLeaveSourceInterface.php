<?php

namespace App\Application\Attendance\Contracts;

/**
 * Teachers on approved leave on a date, as the leave module records them («الإجازات والغياب»). Read only: an
 * approved leave is shown beside the staff day and its lessons are listed for cover — it never writes or alters an
 * attendance record (the end of a leave is not evidence of a return; presence is still recorded by a person).
 */
interface ApprovedLeaveSourceInterface
{
    /** @return array<int, int> teacher id => leave request number */
    public function teachersOnApprovedLeave(int $schoolId, string $date): array;
}
