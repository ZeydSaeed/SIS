<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Application\Timetable\Queries\SuggestSubstitutesHandler;
use App\Application\Timetable\Queries\SuggestSubstitutesQuery;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * «بدلاء مقترحون» for one lesson of a teacher on leave: the timetable's ranked substitutes (qualified, free in that
 * slot, within their daily limit), minus teachers who are themselves on approved leave that day.
 */
final class SuggestLeaveSubstitutesHandler implements QueryHandler
{
    public function __construct(
        private readonly SuggestSubstitutesHandler $substitutes,
        private readonly LeaveReadRepositoryInterface $read,
    ) {}

    /** @return array{candidates: list<array<string, mixed>>, reason: string|null}|null */
    public function handle(Query $query): ?array
    {
        assert($query instanceof SuggestLeaveSubstitutesQuery);
        $insight = $this->substitutes->handle(new SuggestSubstitutesQuery($query->schoolId, $query->academicYearId, $query->scheduleId, $query->date));
        if ($insight === null) {
            return null;
        }
        $away = [];
        foreach ($this->read->awayOn($query->schoolId, $query->date, null) as $row) {
            if ((int) $row['requester_kind'] === RequesterKind::Teacher->value) {
                $away[(int) $row['person_id']] = true;
            }
        }
        $candidates = array_values(array_filter(
            (array) ($insight->data['candidates'] ?? []),
            static fn (array $c): bool => ! isset($away[(int) $c['teacher_id']]),
        ));

        return ['candidates' => $candidates, 'reason' => $insight->data['reason'] ?? null];
    }
}
