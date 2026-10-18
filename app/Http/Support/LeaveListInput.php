<?php

namespace App\Http\Support;

use App\Domain\Leave\Services\LeaveDurationCalculator;
use App\Domain\Leave\ValueObjects\RequesterKind;
use Illuminate\Http\Request;

/**
 * Query-string filters of the leave pages, validated into plain values (allow-lists, integers, dates). Statuses
 * come as `state` (the word `status` is a protected request field).
 */
final class LeaveListInput
{
    private const PER_PAGE_DEFAULT = 25;

    private const PER_PAGE_MAX = 100;

    /** @return array<string, mixed> */
    public static function requests(Request $request, int $yearId, ?int $approverUserId): array
    {
        return [
            'year_id' => $yearId,
            'statuses' => self::ints($request, 'state', 1, 7),
            'kind' => self::kind($request),
            'type_ids' => self::ints($request, 'type', 1, PHP_INT_MAX),
            'q' => self::text($request, 'q'),
            'from' => self::date($request, 'from'),
            'to' => self::date($request, 'to'),
            'own_user_id' => $request->boolean('mine') ? (int) $request->user()?->id : null,
            'approver_user_id' => $request->boolean('awaiting') ? $approverUserId : null,
            'sort' => (string) $request->query('sort', 'request_no'),
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** @return array<string, mixed> */
    public static function balances(Request $request): array
    {
        return ['kind' => self::kind($request), 'type_ids' => self::ints($request, 'type', 1, PHP_INT_MAX), 'q' => self::text($request, 'q')];
    }

    /** @return array<string, mixed> */
    public static function calendar(Request $request): array
    {
        $layers = array_values(array_intersect((array) $request->query('layer', ['holidays', 'staff', 'students']), ['holidays', 'staff', 'students']));

        return [
            'kind' => self::kind($request),
            'type_ids' => self::ints($request, 'type', 1, PHP_INT_MAX),
            'statuses' => self::ints($request, 'state', 2, 4),
            'layers' => $layers,
        ];
    }

    /** @return array<string, mixed> */
    public static function report(Request $request): array
    {
        return ['from' => self::date($request, 'from'), 'to' => self::date($request, 'to'), 'kind' => self::kind($request), 'type_ids' => self::ints($request, 'type', 1, PHP_INT_MAX)];
    }

    /** The first day of the month shown (`month=YYYY-MM`, default this month). */
    public static function month(Request $request): string
    {
        $month = (string) $request->query('month', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1 ? $month.'-01' : now()->startOfMonth()->toDateString();
    }

    public static function calendarView(Request $request): string
    {
        return in_array($request->query('view'), ['month', 'week', 'day', 'list'], true) ? (string) $request->query('view') : 'month';
    }

    /**
     * The window the calendar reads: the month's grid (whole weeks, Sunday first) — at most 6 weeks.
     *
     * @return array{0: string, 1: string}
     */
    public static function calendarWindow(Request $request): array
    {
        $first = new \DateTimeImmutable(self::month($request));
        $start = $first->modify('-'.$first->format('w').' days');
        $last = $first->modify('last day of this month');
        $end = $last->modify('+'.(6 - (int) $last->format('w')).' days');

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    public static function perPage(Request $request): int
    {
        $value = (int) $request->query('per_page', self::PER_PAGE_DEFAULT);

        // Same floor as the attendance lists: the table sends the rows that fit its height, and a floor above what a
        // short window fits would make it ask again for ever.
        return max(3, min(self::PER_PAGE_MAX, $value));
    }

    /**
     * `kind:id` (e.g. `1:42` = teacher 42).
     *
     * @return array{0: RequesterKind|null, 1: int|null}
     */
    public static function person(string $value): array
    {
        if (preg_match('/^([12]):(\d{1,18})$/', $value, $m) !== 1) {
            return [null, null];
        }

        return [RequesterKind::from((int) $m[1]), (int) $m[2]];
    }

    /** The filters echoed back to the page (as the user typed them). @return array<string, mixed> */
    public static function echo(Request $request, int $yearId): array
    {
        return [
            'academic_year_id' => $yearId,
            'state' => self::ints($request, 'state', 1, 7),
            'kind' => self::kind($request),
            'type' => self::ints($request, 'type', 1, PHP_INT_MAX),
            'q' => self::text($request, 'q'),
            'from' => self::date($request, 'from'),
            'to' => self::date($request, 'to'),
            'mine' => $request->boolean('mine'),
            'awaiting' => $request->boolean('awaiting'),
            'sort' => (string) $request->query('sort', 'request_no'),
            'direction' => $request->query('direction') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private static function kind(Request $request): ?int
    {
        return in_array($request->query('kind'), ['1', '2'], true) ? (int) $request->query('kind') : null;
    }

    /** @return list<int> */
    private static function ints(Request $request, string $key, int $min, int $max): array
    {
        $raw = $request->query($key, []);
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_unique(array_filter(
            array_map(static fn ($v): int => is_numeric($v) ? (int) $v : 0, $values),
            static fn (int $v): bool => $v >= $min && $v <= $max,
        )));
    }

    private static function text(Request $request, string $key): string
    {
        return mb_substr(trim((string) $request->query($key, '')), 0, 100);
    }

    private static function date(Request $request, string $key): ?string
    {
        $value = (string) $request->query($key, '');

        return LeaveDurationCalculator::isDate($value) ? $value : null;
    }
}
