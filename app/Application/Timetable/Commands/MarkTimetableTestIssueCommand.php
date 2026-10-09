<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «تجاهل المشكلة» (mark 1) / «تعليمها كمراجعة لاحقة» (mark 2) / clear (null) on a «اختبار الجدول» issue. */
final readonly class MarkTimetableTestIssueCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $issueKey,
        public ?int $mark,
        public ?string $note,
        public ?int $userId,
        public string $idempotencyKey,
    ) {}
}
