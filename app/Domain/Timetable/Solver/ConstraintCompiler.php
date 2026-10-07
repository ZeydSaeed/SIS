<?php

namespace App\Domain\Timetable\Solver;

use App\Domain\Timetable\Constraints\ConstraintRuleCatalogue;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\ValueObjects\ConstraintPriority;
use App\Domain\Timetable\ValueObjects\GenerationMode;

/**
 * Compiles the SIS projection ({@see TimetableBoard}) into a {@see SolverProblem}.
 *
 * Cards: each configured activity splits into blocks (its distribution, e.g. 2+2+1, else weekly ÷ block
 * length plus a remainder). A requirement (teaching assignment × curriculum hours) not covered by an
 * activity becomes an implicit activity — singles, or doubles for a practical subject — so a school with
 * no configuration generates exactly what the builder always placed.
 *
 * Occupancy that never moves: locked lessons, lessons outside the scope and, in optimize / repair, the
 * lessons kept from the current grid. Locked lessons in scope count towards their activity's weekly load.
 *
 * Rules: system defaults (teacher ≤ daily limit, subject ≤ daily limit, no section gaps — HIGH; fewer
 * teacher gaps — VERY_LOW) plus the school's stored rules; for override types the most specific matching
 * rule wins ({@see ConstraintRuleCatalogue::specificity()}). The mode decides which priorities are hard.
 */
final class ConstraintCompiler
{
    /**
     * @param  array{section_ids?: list<int>|null, teacher_ids?: list<int>|null, subject_ids?: list<int>|null}  $scope
     */
    public function compile(TimetableBoard $board, GenerationMode $mode, array $scope = []): SolverProblem
    {
        $settings = $board->settings;
        $periodIndex = array_flip($board->lessonPeriodIds);
        $joins = [];
        foreach ($board->lessonPeriodIds as $i => $periodId) {
            $next = $board->lessonPeriodIds[$i + 1] ?? null;
            $joins[$i] = $next !== null && $board->adjacent($periodId, $next);
        }

        $groupDivision = [];
        $groupsPerDivision = [];
        foreach ($board->groups as $groupId => $group) {
            $groupDivision[$groupId] = $group['division_id'];
            $groupsPerDivision[$group['division_id']] = ($groupsPerDivision[$group['division_id']] ?? 0) + 1;
        }

        [$activities, $notes, $covered] = $this->activities($board);
        $inScope = fn (array $a): bool => $this->inScope($a, $scope);

        // Lessons on the grid: fixed occupancy, or the current position of a block (optimize / repair).
        $fixed = [];
        $lockedCount = [];
        $current = [];
        foreach ($board->schedules as $s) {
            if (! isset($periodIndex[$s['period_id']]) || ! in_array($s['day_of_week'], $settings->days, true)) {
                continue;
            }
            $key = $this->rowActivityKey($s, $activities, $covered);
            $activity = $activities[$key] ?? null;
            $scoped = $activity !== null && $inScope($activity);
            $locked = (bool) ($s['locked'] ?? false);
            if ($scoped && ! $locked) {
                // Regenerated — or, in optimize / repair, the lead row gives its block's starting position
                // (joined-class rows follow their lead row).
                if ($mode->keepsCurrentGrid() && ($s['joined_to'] ?? null) === null) {
                    $current[$key][] = $s;
                }

                continue;
            }
            if ($scoped && $locked && ($s['joined_to'] ?? null) === null) {
                $lockedCount[$key] = ($lockedCount[$key] ?? 0) + 1;
            }
            $fixed[] = $this->fixedItem($s, $periodIndex[$s['period_id']], $key, $settings->weeksOf($s['week_no'] ?? null));
        }

        $cards = [];
        foreach ($activities as $key => $a) {
            if (! $inScope($a)) {
                continue;
            }
            $students = $this->students($board, $a['targets'], $groupsPerDivision);
            [$facilities, $rooms, $blocked] = $this->facilities($board, $a, $students);
            $weekNo = $a['week_pattern'] >= 1 && $a['week_pattern'] <= $settings->cycleWeeks ? $a['week_pattern'] : null;
            $remaining = max(0, $a['weekly'] - ($lockedCount[$key] ?? 0));
            $blocks = $this->blocks($a, $remaining);
            $lead = $a['lead'];
            $coBudget = $a['co_sessions'];
            $n = 0;
            foreach ($blocks as $length) {
                $co = null;
                if ($a['co'] !== null && ($coBudget === null || $coBudget >= $length)) {
                    $co = $a['co'];
                    $coBudget = $coBudget === null ? null : $coBudget - $length;
                }
                $id = $key.'#'.$n++;
                $cards[$id] = new SolverCard(
                    id: $id,
                    activityKey: $key,
                    activityId: $a['id'],
                    subjectId: $a['subject_id'],
                    leadTeacherId: $lead,
                    coTeacherId: $co,
                    teachers: $co === null ? [$lead] : [$lead, $co],
                    targets: $a['targets'],
                    length: $length,
                    facilities: $facilities,
                    rooms: $rooms,
                    weeks: $settings->weeksOf($weekNo),
                    weekNo: $weekNo,
                    students: $students,
                    blockedReason: $blocked,
                );
                if ($blocked !== null) {
                    $notes[] = ['card_id' => $id, 'reason' => $blocked];
                }
            }
        }

        if ($mode->keepsCurrentGrid()) {
            [$cards, $extraFixed] = $this->assignCurrent($cards, $current, $periodIndex, $settings->weeksOf(...), $board);
            array_push($fixed, ...$extraFixed);
        }

        [$teacherRules, $sectionRules, $subjectRules, $placementRules, $pairRules, $teacherPairRules] = $this->rules($board, $mode, $cards);
        [$unavailable, $avoid, $preferred] = $this->availability($board, $periodIndex);

        return new SolverProblem(
            mode: $mode,
            days: $settings->days,
            weeks: $settings->cycleWeeks,
            periodIds: $board->lessonPeriodIds,
            joins: $joins,
            cards: $cards,
            fixed: $fixed,
            groupDivision: $groupDivision,
            unavailable: $unavailable,
            avoid: $avoid,
            preferred: $preferred,
            teacherRules: $teacherRules,
            sectionRules: $sectionRules,
            subjectRules: $subjectRules,
            placementRules: $placementRules,
            pairRules: $pairRules,
            teacherPairRules: $teacherPairRules,
            availabilityWeights: [
                'avoid' => $settings->weight(ConstraintPriority::Medium),
                'preferred' => $settings->weight(ConstraintPriority::Low),
            ],
            compileNotes: $notes,
        );
    }

    /**
     * Configured activities plus implicit ones for uncovered requirements, keyed 'a{id}' / 'r{section}:{subject}:{teacher}',
     * and the coverage map "section:subject:lead teacher" → configured activity key.
     *
     * @return array{0: array<string, array<string, mixed>>, 1: list<array{card_id: string, reason: string}>, 2: array<string, string>}
     */
    private function activities(TimetableBoard $board): array
    {
        $activities = [];
        $notes = [];
        $covered = [];
        foreach ($board->activities as $a) {
            $lead = null;
            $co = null;
            $coSessions = null;
            foreach ($a['teachers'] as $t) {
                if ($t['role'] === 1 && $lead === null) {
                    $lead = $t['teacher_id'];
                } elseif ($co === null && $t['role'] !== 1) {
                    $co = $t['teacher_id'];
                    $coSessions = $t['sessions'];
                }
            }
            $lead ??= $a['teachers'][0]['teacher_id'] ?? null;
            if ($lead === null || $a['targets'] === []) {
                $notes[] = ['card_id' => 'a'.$a['id'], 'reason' => $lead === null ? 'activity_without_teacher' : 'activity_without_targets'];

                continue;
            }
            if ($co === $lead) {
                $co = null;
            }
            $activities['a'.$a['id']] = [
                'id' => $a['id'], 'subject_id' => $a['subject_id'], 'weekly' => $a['weekly'], 'block' => max(1, $a['block']),
                'distribution' => $a['distribution'], 'room_id' => $a['room_id'], 'room_type' => $a['room_type'],
                'workshop_id' => $a['workshop_id'], 'week_pattern' => $a['week_pattern'], 'targets' => $a['targets'],
                'lead' => $lead, 'co' => $co, 'co_sessions' => $coSessions,
            ];
            foreach ($a['targets'] as $target) {
                $covered[$target['section_id'].':'.$a['subject_id'].':'.$lead] = 'a'.$a['id'];
            }
        }

        foreach ($board->requirements as $r) {
            $coverKey = $r['section_id'].':'.$r['subject_id'].':'.$r['teacher_id'];
            if ($r['weekly'] === null || $r['weekly'] < 1 || isset($covered[$coverKey])) {
                continue;
            }
            $activities['r'.$coverKey] = [
                'id' => null, 'subject_id' => $r['subject_id'], 'weekly' => $r['weekly'],
                'block' => $board->isPractical($r['subject_id']) ? 2 : 1, 'distribution' => null,
                'room_id' => null, 'room_type' => null, 'workshop_id' => null, 'week_pattern' => 0,
                'targets' => [['section_id' => $r['section_id'], 'group_id' => null]],
                'lead' => $r['teacher_id'], 'co' => null, 'co_sessions' => null,
            ];
        }

        foreach ($activities as $key => $a) {
            $activities[$key]['targets'] = array_map(static fn (array $t): array => [$t['section_id'], $t['group_id']], $a['targets']);
        }

        return [$activities, $notes, $covered];
    }

    /**
     * The activity a lesson on the grid realises: its own activity id, else the configured activity that
     * covers its section · subject · teacher, else the implicit requirement activity.
     *
     * @param  array<string, string>  $covered
     */
    private function rowActivityKey(array $s, array $activities, array $covered): string
    {
        if (($s['activity_id'] ?? null) !== null && isset($activities['a'.$s['activity_id']])) {
            return 'a'.$s['activity_id'];
        }
        $coverKey = $s['section_id'].':'.$s['subject_id'].':'.$s['teacher_id'];

        return $covered[$coverKey] ?? 'r'.$coverKey;
    }

    private function inScope(array $a, array $scope): bool
    {
        $sections = $scope['section_ids'] ?? null;
        $teachers = $scope['teacher_ids'] ?? null;
        $subjects = $scope['subject_ids'] ?? null;
        if ($sections !== null && array_intersect(array_column($a['targets'], 0), $sections) === []) {
            return false;
        }
        if ($teachers !== null && array_intersect(array_filter([$a['lead'], $a['co']]), $teachers) === []) {
            return false;
        }

        return $subjects === null || in_array($a['subject_id'], $subjects, true);
    }

    /** @return list<int> block lengths covering `weekly` lessons */
    private function blocks(array $a, int $weekly): array
    {
        if ($weekly <= 0) {
            return [];
        }
        if ($a['distribution'] !== null && $a['distribution'] !== [] && array_sum($a['distribution']) === $a['weekly'] && $weekly === $a['weekly']) {
            return $a['distribution'];
        }
        $block = min($a['block'], $weekly);
        $blocks = array_fill(0, intdiv($weekly, $block), $block);
        if ($weekly % $block > 0) {
            $blocks[] = $weekly % $block;
        }

        return $blocks;
    }

    /** @param  list<array{0: int, 1: int|null}>  $targets */
    private function students(TimetableBoard $board, array $targets, array $groupsPerDivision): int
    {
        $total = 0;
        foreach ($targets as [$sectionId, $groupId]) {
            $sectionStudents = $board->sectionInfo[$sectionId]['students'] ?? 0;
            if ($groupId === null) {
                $total += $sectionStudents;

                continue;
            }
            $group = $board->groups[$groupId] ?? null;
            $total += $group['student_count'] ?? (int) ceil($sectionStudents / max(1, $groupsPerDivision[$group['division_id'] ?? 0] ?? 1));
        }

        return $total;
    }

    /**
     * Candidate facilities: the activity's workshop (its room when it has one), its room, or every room of
     * its room type — only those big enough. Capacity is hard: no candidate → the block is blocked.
     *
     * @return array{0: list<string>, 1: array<string, int|null>, 2: string|null}
     */
    private function facilities(TimetableBoard $board, array $a, int $students): array
    {
        if ($a['workshop_id'] !== null) {
            $workshop = $board->workshops[$a['workshop_id']] ?? null;
            if ($workshop === null) {
                return [[], [], 'workshop_unknown'];
            }
            if ($students > $workshop['safety_capacity']) {
                return [[], [], 'workshop_capacity'];
            }

            return $workshop['room_id'] !== null
                ? [['R'.$workshop['room_id']], ['R'.$workshop['room_id'] => $workshop['room_id']], null]
                : [['W'.$a['workshop_id']], ['W'.$a['workshop_id'] => null], null];
        }
        if ($a['room_id'] !== null) {
            $capacity = $board->rooms[$a['room_id']]['capacity'] ?? null;
            if ($capacity !== null && $students > $capacity) {
                return [[], [], 'room_capacity'];
            }

            return [['R'.$a['room_id']], ['R'.$a['room_id'] => $a['room_id']], null];
        }
        if ($a['room_type'] !== null) {
            $keys = [];
            $rooms = [];
            foreach ($board->rooms as $room) {
                if ($room['room_type'] === $a['room_type'] && ($room['capacity'] === null || $room['capacity'] >= $students)) {
                    $keys[] = 'R'.$room['id'];
                    $rooms['R'.$room['id']] = $room['id'];
                }
            }

            return $keys === [] ? [[], [], 'no_room_of_type'] : [$keys, $rooms, null];
        }

        return [[], [], null];
    }

    /** @param  list<int>  $weeks */
    private function fixedItem(array $s, int $index, string $key, array $weeks): array
    {
        $lead = ($s['joined_to'] ?? null) === null;

        return [
            'key' => 'F'.$s['id'],
            'teachers' => TimetableBoard::busyTeachers($s),
            'targets' => [[$s['section_id'], $s['group_id'] ?? null]],
            'facility' => $lead && ($s['room_id'] ?? null) !== null ? 'R'.$s['room_id'] : null,
            'day' => $s['day_of_week'],
            'index' => $index,
            'weeks' => $weeks,
            'subject_id' => $s['subject_id'],
            'activity_key' => $key,
        ];
    }

    /**
     * Optimize / repair: each block takes its current position from the grid (runs of consecutive lessons of
     * its activity on one day); lessons beyond the activity's load stay fixed — improve never removes lessons.
     *
     * @param  array<string, SolverCard>  $cards
     * @param  array<string, list<array<string, mixed>>>  $current
     * @return array{0: array<string, SolverCard>, 1: list<array<string, mixed>>}
     */
    private function assignCurrent(array $cards, array $current, array $periodIndex, callable $weeksOf, TimetableBoard $board): array
    {
        $extra = [];
        foreach ($current as $key => $rows) {
            usort($rows, static fn (array $a, array $b): int => [$a['week_no'] ?? 0, $a['day_of_week'], $periodIndex[$a['period_id']]] <=> [$b['week_no'] ?? 0, $b['day_of_week'], $periodIndex[$b['period_id']]]);
            $runs = [];
            foreach ($rows as $row) {
                $last = $runs === [] ? null : $runs[count($runs) - 1];
                $index = $periodIndex[$row['period_id']];
                if ($last !== null && $last['day'] === $row['day_of_week'] && $last['week'] === ($row['week_no'] ?? null) && $last['end'] === $index - 1) {
                    $runs[count($runs) - 1]['end'] = $index;
                    $runs[count($runs) - 1]['rows'][] = $row;
                } else {
                    $runs[] = ['day' => $row['day_of_week'], 'week' => $row['week_no'] ?? null, 'start' => $index, 'end' => $index, 'rows' => [$row]];
                }
            }

            $free = array_filter($cards, static fn (SolverCard $c): bool => $c->activityKey === $key && $c->initial === null);
            foreach ($runs as $run) {
                $offset = 0;
                $length = $run['end'] - $run['start'] + 1;
                while ($offset < $length) {
                    $match = null;
                    foreach ($free as $id => $card) {
                        if ($card->length <= $length - $offset && ($match === null || $card->length > $cards[$match]->length)) {
                            $match = $id;
                        }
                    }
                    if ($match === null) {
                        foreach (array_slice($run['rows'], $offset) as $row) {
                            $extra[] = $this->fixedItem($row, $periodIndex[$row['period_id']], $key, $weeksOf($row['week_no'] ?? null));
                        }
                        break;
                    }
                    $room = $run['rows'][$offset]['room_id'] ?? null;
                    $facility = $room !== null && in_array('R'.$room, $cards[$match]->facilities, true) ? 'R'.$room : ($cards[$match]->facilities[0] ?? null);
                    $cards[$match] = $cards[$match]->withInitial([$run['day'], $run['start'] + $offset, $facility]);
                    $offset += $cards[$match]->length;
                    unset($free[$match]);
                }
            }
        }

        return [$cards, $extra];
    }

    /**
     * @param  array<string, SolverCard>  $cards
     * @return array{0: array<int, list<array<string, mixed>>>, 1: array<int, list<array<string, mixed>>>, 2: array<int, array<int, list<array<string, mixed>>>>, 3: array<string, list<array<string, mixed>>>, 4: list<array<string, mixed>>, 5: array<int, list<array<string, mixed>>>}
     */
    private function rules(TimetableBoard $board, GenerationMode $mode, array $cards): array
    {
        $settings = $board->settings;
        $compiled = [
            $this->compileRule(null, 'teacher_max_per_day', ConstraintPriority::High, [], ['max' => $settings->maxTeacherPerDay], $mode, $board),
            $this->compileRule(null, 'subject_max_per_day', ConstraintPriority::High, [], ['max' => $settings->maxSubjectPerDay], $mode, $board),
            $this->compileRule(null, 'section_no_gaps', ConstraintPriority::High, [], [], $mode, $board),
            $this->compileRule(null, 'teacher_max_gaps_per_day', ConstraintPriority::VeryLow, [], ['max' => 0], $mode, $board),
        ];
        foreach ($board->rules as $r) {
            if (! ConstraintRuleCatalogue::exists($r['rule_type'])) {
                continue;
            }
            $compiled[] = $this->compileRule($r['id'], $r['rule_type'], ConstraintPriority::from($r['priority']), $r['scope'], $r['params'], $mode, $board);
        }

        $teachers = [];
        $sections = [];
        $subjectsBySection = [];
        foreach ($cards as $card) {
            foreach ($card->teachers as $t) {
                $teachers[$t] = true;
            }
            foreach ($card->sectionIds() as $s) {
                $sections[$s] = true;
                $subjectsBySection[$s][$card->subjectId] = true;
            }
        }

        $teacherRules = [];
        foreach (array_keys($teachers) as $t) {
            $teacherRules[$t] = $this->resolve($compiled, ['teacher_day', 'teacher_week'], fn (array $rule): bool => $this->matchesTeacher($rule, $t));
        }

        $sectionRules = [];
        $subjectRules = [];
        foreach (array_keys($sections) as $s) {
            $sectionRules[$s] = array_values(array_filter(
                $this->resolve($compiled, ['section_day'], fn (array $rule): bool => ($rule['scope']['subject_id'] ?? null) === null && $rule['type'] !== 'subject_max_per_day' && $this->matchesSection($board, $rule, $s)),
                static fn (array $rule): bool => $rule['type'] !== 'subject_max_per_day',
            ));
            foreach (array_keys($subjectsBySection[$s]) as $subject) {
                $subjectRules[$s][$subject] = [
                    ...$this->resolve($compiled, ['section_day'], fn (array $rule): bool => $rule['type'] === 'subject_max_per_day'
                        && in_array($rule['scope']['subject_id'] ?? null, [null, $subject], true) && $this->matchesSection($board, $rule, $s)),
                    ...array_values(array_filter($compiled, fn (array $rule): bool => $rule['kind'] === 'section_week'
                        && ($rule['scope']['subject_id'] ?? null) === $subject && $this->matchesSection($board, $rule, $s))),
                ];
            }
        }

        $placementRules = [];
        $placement = array_values(array_filter($compiled, static fn (array $rule): bool => $rule['kind'] === 'placement'));
        foreach ($cards as $id => $card) {
            foreach ($placement as $rule) {
                if ($this->matchesCard($board, $rule, $card)) {
                    $placementRules[$id][] = $rule;
                }
            }
        }

        $pairRules = [];
        $teacherPairRules = [];
        foreach ($compiled as $rule) {
            if ($rule['kind'] === 'pair') {
                $rule['a'] = 'a'.$rule['scope']['activity_id'];
                $rule['b'] = 'a'.$rule['scope']['other_activity_id'];
                $pairRules[] = $rule;
            } elseif ($rule['kind'] === 'teacher_pair') {
                $teacherPairRules[(int) $rule['scope']['teacher_id']][] = $rule;
            }
        }

        return [$teacherRules, $sectionRules, $subjectRules, $placementRules, $pairRules, $teacherPairRules];
    }

    private function compileRule(?int $id, string $type, ConstraintPriority $priority, array $scope, array $params, GenerationMode $mode, TimetableBoard $board): array
    {
        $scope = array_merge(array_fill_keys(ConstraintRuleCatalogue::SCOPES, null), array_intersect_key($scope, array_flip(ConstraintRuleCatalogue::SCOPES)));

        return [
            'id' => $id,
            'type' => $type,
            'kind' => ConstraintRuleCatalogue::kind($type),
            'priority' => $priority->value,
            'hard' => $mode->isHard($priority),
            'weight' => $board->settings->weight($priority),
            'params' => $params,
            'scope' => $scope,
            'specificity' => $id === null ? -1 : ConstraintRuleCatalogue::specificity($scope),
            'source' => $id === null ? 'system' : $this->sourceOf($scope),
        ];
    }

    /** The level a rule comes from, for «هذا القيد جاء من …». */
    private function sourceOf(array $scope): string
    {
        foreach (['activity_id' => 'activity', 'teacher_id' => 'teacher', 'room_id' => 'room', 'subject_id' => 'subject', 'section_id' => 'section', 'class_id' => 'class', 'grade_level_id' => 'grade', 'department_id' => 'department', 'branch_id' => 'branch'] as $column => $label) {
            if (($scope[$column] ?? null) !== null) {
                return $label;
            }
        }

        return 'school';
    }

    /**
     * Override types keep the single most specific matching rule; additive kinds keep all.
     *
     * @param  list<array<string, mixed>>  $compiled
     * @param  list<string>  $kinds
     * @return list<array<string, mixed>>
     */
    private function resolve(array $compiled, array $kinds, callable $matches): array
    {
        $best = [];
        $all = [];
        foreach ($compiled as $rule) {
            if (! in_array($rule['kind'], $kinds, true) || ! $matches($rule)) {
                continue;
            }
            if (! ConstraintRuleCatalogue::isOverride($rule['type'])) {
                $all[] = $rule;

                continue;
            }
            $current = $best[$rule['type']] ?? null;
            if ($current === null || [$rule['specificity'], $rule['id'] ?? 0] > [$current['specificity'], $current['id'] ?? 0]) {
                $best[$rule['type']] = $rule;
            }
        }

        return [...array_values($best), ...$all];
    }

    private function matchesTeacher(array $rule, int $teacherId): bool
    {
        return in_array($rule['scope']['teacher_id'], [null, $teacherId], true);
    }

    private function matchesSection(TimetableBoard $board, array $rule, int $sectionId): bool
    {
        $scope = $rule['scope'];
        if ($scope['section_id'] !== null && $scope['section_id'] !== $sectionId) {
            return false;
        }
        $info = $board->sectionInfo[$sectionId] ?? null;
        if ($info === null) {
            return $scope['branch_id'] === null && $scope['department_id'] === null && $scope['grade_level_id'] === null && $scope['class_id'] === null;
        }

        return ($scope['class_id'] === null || $scope['class_id'] === $info['class_id'])
            && ($scope['grade_level_id'] === null || $scope['grade_level_id'] === $info['grade_level_id'])
            && ($scope['branch_id'] === null || in_array($scope['branch_id'], $info['branch_ids'], true))
            && ($scope['department_id'] === null || in_array($scope['department_id'], $info['department_ids'], true));
    }

    private function matchesCard(TimetableBoard $board, array $rule, SolverCard $card): bool
    {
        $scope = $rule['scope'];
        if ($scope['teacher_id'] !== null && ! in_array($scope['teacher_id'], $card->teachers, true)) {
            return false;
        }
        if ($scope['subject_id'] !== null && $scope['subject_id'] !== $card->subjectId) {
            return false;
        }
        if ($scope['activity_id'] !== null && $scope['activity_id'] !== $card->activityId) {
            return false;
        }
        if ($scope['room_id'] !== null && ! in_array('R'.$scope['room_id'], $card->facilities, true)) {
            return false;
        }
        foreach ($card->sectionIds() as $sectionId) {
            if ($this->matchesSection($board, $rule, $sectionId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: array<string, array<string, true>>, 1: array<string, array<string, true>>, 2: array<string, array<string, true>>}
     */
    private function availability(TimetableBoard $board, array $periodIndex): array
    {
        $maps = [1 => [], 2 => [], 3 => []];
        foreach ($board->availability as $a) {
            if (! isset($periodIndex[$a['period_id']])) {
                continue;
            }
            $resource = match (true) {
                $a['teacher_id'] !== null => 'T'.$a['teacher_id'],
                $a['room_id'] !== null => 'R'.$a['room_id'],
                $a['section_id'] !== null => 'S'.$a['section_id'],
                default => 'W'.$a['workshop_id'],
            };
            foreach ($board->settings->weeksOf($a['week_no']) as $week) {
                $maps[$a['kind']][$resource][$week.':'.$a['day'].':'.$periodIndex[$a['period_id']]] = true;
            }
        }

        return [$maps[1], $maps[2], $maps[3]];
    }
}
