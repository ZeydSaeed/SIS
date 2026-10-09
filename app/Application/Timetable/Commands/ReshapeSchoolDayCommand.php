<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Domain\Timetable\Services\BellScheduleCalculator;
use App\Domain\Timetable\ValueObjects\PeriodPresentation;

/**
 * «الاستراحات وإعدادات الحصص»: one re-timing operation on the school day, applied with automatic shifting
 * ({@see BellScheduleCalculator}):
 *
 * - insert_break  afterPeriodId (null = before the first), minutes, presentation
 * - move_break    periodId (the break), afterPeriodId
 * - resize        periodId, minutes (a break or a single lesson)
 * - remove_break  periodId (retired, never deleted)
 * - retime        periodId, startTime, endTime, cascade (variable timing / a single period)
 * - fixed_pattern startTime (day start), minutes (every lesson); breaks keep their lengths
 */
final readonly class ReshapeSchoolDayCommand implements Command
{
    public const OPERATIONS = ['insert_break', 'move_break', 'resize', 'remove_break', 'retime', 'fixed_pattern'];

    public function __construct(
        public int $schoolId,
        public string $operation,
        public ?int $periodId,
        public ?int $afterPeriodId,
        public ?int $minutes,
        public ?string $startTime,
        public ?string $endTime,
        public bool $cascade,
        public ?PeriodPresentation $presentation,
        public string $idempotencyKey,
    ) {}
}
