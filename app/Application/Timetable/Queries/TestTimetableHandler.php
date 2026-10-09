<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Repositories\TimetableTestMarkRepositoryInterface;
use App\Domain\Timetable\Testing\TimetableTestRunner;

/**
 * Runs «اختبار الجدول» on the school-year board: one board load, the display catalogue (abbreviations,
 * section capacities) and the user's marks; everything else is pure Domain work in memory.
 */
final class TestTimetableHandler implements QueryHandler
{
    public function __construct(
        private readonly TimetableBoardLoader $boards,
        private readonly GetTimetableDisplayCatalogHandler $display,
        private readonly TimetableTestMarkRepositoryInterface $marks,
        private readonly TimetableTestRunner $runner,
    ) {}

    /** @return array<string, mixed> */
    public function handle(Query $query): array
    {
        assert($query instanceof TestTimetableQuery);
        $board = $this->boards->load($query->schoolId, $query->academicYearId);
        $started = hrtime(true);
        $report = $this->runner->run(
            $board,
            $this->display->handle(new GetTimetableDisplayCatalogQuery($query->schoolId, $query->academicYearId)),
            $this->marks->active($query->schoolId, $query->academicYearId),
        );

        return $report + ['elapsed_ms' => (int) ((hrtime(true) - $started) / 1_000_000), 'tested_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM)];
    }
}
