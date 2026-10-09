<?php

namespace App\Domain\Timetable\Testing;

use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Services\TimetableAdvisor;
use App\Domain\Timetable\Services\TimetableAuditor;

/**
 * «اختبار الجدول» — one report before (and after) generation: the advisor's feasibility findings, the auditor's
 * grid issues and the workbench checks (rooms, capacity, the school day, breaks, the week's balance, data), each
 * with a cause, remedies computed from the board ({@see RemedyFinder}) and alternatives. Severities:
 * critical (generation is refused) · error · warning · suggestion · optimization.
 *
 * Marks («تجاهل» / «مراجعة لاحقاً») only label an issue; a critical issue still stops generation when ignored —
 * the engine's hard rules are not switched off from a report.
 */
final class TimetableTestRunner
{
    /** Advisor finding → [severity, category]. */
    private const ADVISOR = [
        'no_lesson_periods' => [TestIssue::CRITICAL, 'periods'],
        'teacher_inactive' => [TestIssue::CRITICAL, 'teachers'],
        'teacher_not_qualified' => [TestIssue::CRITICAL, 'teachers'],
        'weekly_load_missing' => [TestIssue::WARNING, 'subjects'],
        'practical_no_double_slot' => [TestIssue::CRITICAL, 'periods'],
        'subject_split_between_teachers' => [TestIssue::WARNING, 'subjects'],
        'section_overbooked' => [TestIssue::CRITICAL, 'sections'],
        'teacher_overbooked' => [TestIssue::CRITICAL, 'teachers'],
        'teacher_over_quota' => [TestIssue::WARNING, 'teachers'],
        'teacher_tight' => [TestIssue::SUGGESTION, 'teachers'],
        'teacher_under_quota' => [TestIssue::SUGGESTION, 'teachers'],
        'subject_over_daily_limit' => [TestIssue::CRITICAL, 'subjects'],
        'section_without_lessons' => [TestIssue::SUGGESTION, 'sections'],
        'activity_blocked' => [TestIssue::CRITICAL, 'constraints'],
        'teacher_time_off_overbooked' => [TestIssue::CRITICAL, 'teachers'],
        'facility_overbooked' => [TestIssue::CRITICAL, 'rooms'],
    ];

    /** Auditor issue → [severity, category]. */
    private const AUDITOR = [
        'teacher_double_booked' => [TestIssue::ERROR, 'teachers'],
        'section_double_booked' => [TestIssue::ERROR, 'sections'],
        'teacher_inactive' => [TestIssue::ERROR, 'teachers'],
        'teacher_not_assigned' => [TestIssue::ERROR, 'teachers'],
        'lesson_in_break' => [TestIssue::ERROR, 'periods'],
        'lesson_over_placed' => [TestIssue::WARNING, 'subjects'],
        'lesson_under_placed' => [TestIssue::SUGGESTION, 'subjects'],
        'teacher_day_overload' => [TestIssue::WARNING, 'teachers'],
        'teacher_gap' => [TestIssue::OPTIMIZATION, 'teachers'],
        'subject_day_repeat' => [TestIssue::WARNING, 'subjects'],
        'practical_split' => [TestIssue::OPTIMIZATION, 'subjects'],
        'section_day_gap' => [TestIssue::OPTIMIZATION, 'sections'],
    ];

    private const SEVERITY_ORDER = [TestIssue::CRITICAL => 0, TestIssue::ERROR => 1, TestIssue::WARNING => 2, TestIssue::SUGGESTION => 3, TestIssue::OPTIMIZATION => 4];

    public function __construct(
        private readonly TimetableAdvisor $advisor,
        private readonly TimetableAuditor $auditor,
        private readonly PlacementChecks $placements,
        private readonly DayChecks $days,
        private readonly DataChecks $data,
    ) {}

    /**
     * @param  array<string, array<int|string, array<string, mixed>>>  $display  display catalogue (abbreviations, capacities)
     * @param  array<string, int>  $marks  issue key → 1 (ignored) / 2 (review later)
     * @return array<string, mixed>
     */
    public function run(TimetableBoard $board, array $display = [], array $marks = []): array
    {
        $remedies = new RemedyFinder($board);
        $advice = $this->advisor->advise($board);
        $sectionCapacity = [];
        foreach ($display['sections'] ?? [] as $row) {
            $sectionCapacity[(int) $row['id']] = $row['capacity'] ?? null;
        }

        $issues = [
            ...array_map(fn (array $f): array => $this->fromAdvisor($board, $remedies, $f), $advice['findings']),
            ...array_map(fn (array $i): array => $this->fromAuditor($board, $remedies, $i), $this->auditor->audit($board)),
            ...$this->placements->run($board, $remedies, $sectionCapacity),
            ...$this->days->run($board, $remedies),
            ...$this->data->run($display, self::used($board)),
        ];

        $unique = [];
        foreach ($issues as $issue) {
            $issue['mark'] = match ($marks[$issue['key']] ?? null) {
                1 => 'ignored',
                2 => 'review',
                default => null,
            };
            $unique[$issue['key']] = $issue;
        }
        $issues = array_values($unique);
        usort($issues, static fn (array $a, array $b): int => [self::SEVERITY_ORDER[$a['severity']], $a['category'], $a['code'], $a['key']]
            <=> [self::SEVERITY_ORDER[$b['severity']], $b['category'], $b['code'], $b['key']]);

        return self::summary($issues, $advice);
    }

    /** @return array<string, mixed> */
    private function fromAdvisor(TimetableBoard $board, RemedyFinder $remedies, array $f): array
    {
        [$severity, $category] = self::ADVISOR[$f['code']] ?? [TestIssue::WARNING, 'constraints'];
        $fixes = [];
        $alternatives = [];
        switch ($f['code']) {
            case 'teacher_overbooked':
            case 'teacher_over_quota':
                foreach ($remedies->relievingTeachers((int) $f['teacher_id']) as $p) {
                    $fixes[] = TestIssue::fix('reassign_section', 'open', ['page' => 'teachers', 'teacher_id' => $p['teacher_id']], true, $p);
                }
                $alternatives = ['raise_teacher_limit', 'reduce_weekly_hours'];
                break;
            case 'practical_no_double_slot':
                $break = $remedies->breakToShorten();
                if ($break !== null) {
                    $fixes[] = TestIssue::fix('shorten_break', 'reshape_day', ['operation' => 'resize', 'period_id' => $break['period_id'], 'minutes' => $break['to']], false, $break);
                }
                $fixes[] = TestIssue::fix('raise_changeover', 'update_settings', ['double_changeover_minutes' => $break['minutes'] ?? 15], false);
                break;
            case 'subject_over_daily_limit':
                $needed = (int) ceil(($f['count'] ?? 0) / max(1, count($board->settings->days)));
                $fixes[] = TestIssue::fix('raise_subject_limit', 'update_settings', ['max_subject_per_day' => max($needed, $board->settings->maxSubjectPerDay + 1)], false, ['needed' => $needed]);
                $alternatives = ['reduce_weekly_hours'];
                break;
            case 'section_overbooked':
                $deficit = (int) ($f['count'] ?? 0) - (int) ($f['limit'] ?? 0);
                $fixes[] = TestIssue::fix('add_lesson_periods', 'open_periods', [], false, ['deficit' => $deficit, 'per_day' => (int) ceil($deficit / max(1, count($board->settings->days)))]);
                $alternatives = ['reduce_weekly_hours', 'add_working_day'];
                break;
            case 'teacher_inactive':
            case 'teacher_not_qualified':
            case 'teacher_time_off_overbooked':
                $fixes[] = TestIssue::fix('open_teacher', 'open', ['page' => 'teachers', 'teacher_id' => $f['teacher_id']], true);
                break;
            case 'weekly_load_missing':
            case 'subject_split_between_teachers':
                $fixes[] = TestIssue::fix('open_curriculum', 'open', ['page' => 'curriculum'], true);
                break;
            case 'no_lesson_periods':
                $fixes[] = TestIssue::fix('setup_periods', 'open_periods', [], true);
                break;
            case 'activity_blocked':
            case 'facility_overbooked':
                $fixes[] = TestIssue::fix('open_constraints', 'open_engine', ['sheet' => $f['code'] === 'facility_overbooked' ? 'places' : 'activities'], true);
                break;
        }

        return TestIssue::make('input', $severity, $category, $f['code'], [
            'section_id' => $f['section_id'], 'teacher_id' => $f['teacher_id'], 'subject_id' => $f['subject_id'],
            'count' => $f['count'], 'limit' => $f['limit'], 'detail' => $f['detail'] ?? null,
        ], $f['code'], $fixes, $alternatives);
    }

    /** @return array<string, mixed> */
    private function fromAuditor(TimetableBoard $board, RemedyFinder $remedies, array $i): array
    {
        [$severity, $category] = self::AUDITOR[$i['code']] ?? [TestIssue::WARNING, 'constraints'];
        $fixes = [];
        $alternatives = [];
        $byId = [];
        foreach ($board->schedules as $s) {
            $byId[$s['id']] = $s;
        }
        $lesson = $byId[end($i['schedule_ids'])] ?? null;
        if ($lesson !== null && in_array($i['code'], ['teacher_double_booked', 'section_double_booked', 'lesson_in_break', 'teacher_day_overload', 'subject_day_repeat'], true)) {
            foreach ($remedies->freeSlots($lesson, 3) as $slot) {
                $fixes[] = TestIssue::fix('move_lesson', 'patch_schedule', ['schedule_id' => $lesson['id'], 'day_of_week' => $slot['day'], 'period_id' => $slot['period_id']], true, ['reason' => $slot['reason']]);
            }
            $fixes[] = TestIssue::fix('unplace_lesson', 'cancel_schedule', ['schedule_id' => $lesson['id']], false);
            $alternatives = ['regenerate_repair'];
        } elseif ($i['code'] === 'lesson_over_placed' && $lesson !== null) {
            $fixes[] = TestIssue::fix('unplace_lesson', 'cancel_schedule', ['schedule_id' => $lesson['id']], false);
        } elseif ($i['code'] === 'lesson_under_placed' && $i['section_id'] !== null) {
            $fixes[] = TestIssue::fix('auto_place_section', 'auto_place', ['section_ids' => [$i['section_id']]], true);
        } elseif (in_array($i['code'], ['teacher_gap', 'section_day_gap', 'practical_split'], true)) {
            $fixes[] = TestIssue::fix('optimize', 'generate', ['mode' => 4], true);
        } elseif (in_array($i['code'], ['teacher_inactive', 'teacher_not_assigned'], true)) {
            $fixes[] = TestIssue::fix('open_teacher', 'open', ['page' => 'teachers', 'teacher_id' => $i['teacher_id']], true);
        }

        return TestIssue::make('grid', $severity, $category, $i['code'], [
            'section_id' => $i['section_id'], 'teacher_id' => $i['teacher_id'], 'subject_id' => $i['subject_id'],
            'day' => $i['day'], 'period_id' => $i['period_id'], 'schedule_ids' => $i['schedule_ids'], 'count' => $i['count'],
        ], $i['code'], $fixes, $alternatives);
    }

    /** @return array<string, array<int, true>> ids shown on the timetable, by display kind */
    private static function used(TimetableBoard $board): array
    {
        $used = ['teachers' => [], 'subjects' => [], 'rooms' => []];
        foreach ($board->requirements as $r) {
            $used['teachers'][$r['teacher_id']] = true;
            $used['subjects'][$r['subject_id']] = true;
        }
        foreach ($board->schedules as $s) {
            $used['teachers'][$s['teacher_id']] = true;
            $used['subjects'][$s['subject_id']] = true;
            if (($s['room_id'] ?? null) !== null) {
                $used['rooms'][$s['room_id']] = true;
            }
        }

        return $used;
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    private static function summary(array $issues, array $advice): array
    {
        $counts = array_fill_keys(TestIssue::SEVERITIES, 0);
        $categories = array_fill_keys(TestIssue::CATEGORIES, ['total' => 0, 'worst' => null]);
        $open = 0;
        foreach ($issues as $issue) {
            $counts[$issue['severity']]++;
            $category = &$categories[$issue['category']];
            $category['total']++;
            if ($category['worst'] === null || self::SEVERITY_ORDER[$issue['severity']] < self::SEVERITY_ORDER[$category['worst']]) {
                $category['worst'] = $issue['severity'];
            }
            unset($category);
            if ($issue['mark'] === null && in_array($issue['severity'], [TestIssue::CRITICAL, TestIssue::ERROR], true)) {
                $open++;
            }
        }
        $penalty = 25 * $counts[TestIssue::CRITICAL] + 8 * $counts[TestIssue::ERROR] + 2 * $counts[TestIssue::WARNING] + (int) ceil(0.5 * $counts[TestIssue::SUGGESTION]);

        return [
            'verdict' => $counts[TestIssue::CRITICAL] > 0 ? 'blocked' : ($open > 0 || $counts[TestIssue::WARNING] > 0 ? 'ready_with_issues' : 'ready'),
            'can_generate' => $counts[TestIssue::CRITICAL] === 0,
            // Readiness of the input (advisor) less a weighted penalty per open problem.
            'score' => max(0, min(100, (int) $advice['readiness']['overall'] - $penalty)),
            'counts' => $counts,
            'categories' => $categories,
            'readiness' => $advice['readiness'],
            'totals' => $advice['totals'],
            'issues' => $issues,
        ];
    }
}
