<?php

namespace App\Domain\Timetable\Testing;

/**
 * One finding of «اختبار الجدول». Plain data (the UI renders the Arabic text from `code` + params):
 *
 * - key       stable identity («تجاهل» / «مراجعة لاحقاً» marks are stored against it)
 * - severity  critical (stops generation) · error (must be fixed) · warning (allowed, not ideal) ·
 *             suggestion (an improvement) · optimization (a better timetable is possible)
 * - category  teachers · subjects · classes · sections · rooms · periods · days · breaks · constraints · data
 * - cause     why it happens (code), shown under the problem
 * - fixes     concrete remedies the server computed — {code, action, params, safe, explain}; `safe` = applying it
 *             cannot remove or overwrite anything (the user still confirms)
 * - alternatives  other ways out (codes)
 */
final class TestIssue
{
    public const CRITICAL = 'critical';

    public const ERROR = 'error';

    public const WARNING = 'warning';

    public const SUGGESTION = 'suggestion';

    public const OPTIMIZATION = 'optimization';

    public const SEVERITIES = [self::CRITICAL, self::ERROR, self::WARNING, self::SUGGESTION, self::OPTIMIZATION];

    public const CATEGORIES = ['teachers', 'subjects', 'classes', 'sections', 'rooms', 'periods', 'days', 'breaks', 'constraints', 'data'];

    /**
     * @param  array{section_id?: int|null, teacher_id?: int|null, subject_id?: int|null, room_id?: int|null, period_id?: int|null, day?: int|null, schedule_ids?: list<int>, count?: int|null, limit?: int|null, detail?: string|null}  $at
     * @param  list<array{code: string, action: string, params: array<string, mixed>, safe: bool, explain?: array<string, mixed>}>  $fixes
     * @param  list<string>  $alternatives
     * @return array<string, mixed>
     */
    public static function make(string $source, string $severity, string $category, string $code, array $at = [], ?string $cause = null, array $fixes = [], array $alternatives = []): array
    {
        $refs = [
            'section_id' => $at['section_id'] ?? null,
            'teacher_id' => $at['teacher_id'] ?? null,
            'subject_id' => $at['subject_id'] ?? null,
            'room_id' => $at['room_id'] ?? null,
            'period_id' => $at['period_id'] ?? null,
            'day' => $at['day'] ?? null,
        ];
        $scheduleIds = array_values(array_map('intval', $at['schedule_ids'] ?? []));
        sort($scheduleIds);

        return [
            'key' => self::key($source, $code, $refs, $scheduleIds, $at['detail'] ?? null),
            'severity' => $severity,
            'category' => $category,
            'code' => $code,
            'source' => $source,
            ...$refs,
            'schedule_ids' => $scheduleIds,
            'count' => $at['count'] ?? null,
            'limit' => $at['limit'] ?? null,
            'detail' => $at['detail'] ?? null,
            'cause' => $cause ?? $code,
            'blocks_generation' => $severity === self::CRITICAL,
            'fixes' => $fixes,
            'alternatives' => $alternatives,
            'mark' => null,
        ];
    }

    /** @param  array<string, mixed>  $params */
    public static function fix(string $code, string $action, array $params, bool $safe, array $explain = []): array
    {
        return ['code' => $code, 'action' => $action, 'params' => $params, 'safe' => $safe, 'explain' => $explain];
    }

    /** @param  array<string, int|null>  $refs */
    private static function key(string $source, string $code, array $refs, array $scheduleIds, ?string $detail): string
    {
        $parts = [$source, $code];
        foreach ($refs as $name => $value) {
            if ($value !== null) {
                $parts[] = substr($name, 0, 2).$value;
            }
        }
        if ($scheduleIds !== []) {
            $parts[] = 'x'.implode('-', array_slice($scheduleIds, 0, 6));
        }
        if ($detail !== null) {
            $parts[] = substr(md5($detail), 0, 8);
        }

        return substr(implode(':', $parts), 0, 200);
    }
}
