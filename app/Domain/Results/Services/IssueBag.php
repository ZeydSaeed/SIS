<?php

namespace App\Domain\Results\Services;

/** Collects reconciliation issues by code, each with the enrollments it concerns (at most 100 listed). */
final class IssueBag
{
    private const LISTED = 100;

    /** @var array<string, list<int>> */
    private array $byCode = [];

    /** @var array<string, int> */
    private array $counts = [];

    public function add(string $code, int $enrollmentId): void
    {
        $this->counts[$code] = ($this->counts[$code] ?? 0) + 1;
        if (count($this->byCode[$code] ?? []) < self::LISTED && $enrollmentId > 0) {
            $this->byCode[$code][] = $enrollmentId;
        }
    }

    /**
     * @param  list<string>  $codes
     * @return list<string> those of the codes that occurred
     */
    public function occurred(array $codes): array
    {
        return array_values(array_filter($codes, fn (string $code): bool => isset($this->counts[$code])));
    }

    /** @return list<array{code: string, count: int, enrollment_ids: list<int>}> */
    public function all(): array
    {
        $issues = [];
        foreach ($this->counts as $code => $count) {
            $issues[] = ['code' => $code, 'count' => $count, 'enrollment_ids' => $this->byCode[$code] ?? []];
        }

        return $issues;
    }
}
