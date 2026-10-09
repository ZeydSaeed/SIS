<?php

namespace App\Application\Imports\Support;

/**
 * Who imports where: the school, the academic year (when the kind needs one), the user — plus a per-batch memo so a
 * profile looks each reference up once (e.g. all subject codes) instead of once per row.
 */
final class ImportContext
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(
        public readonly int $schoolId,
        public readonly ?int $academicYearId,
        public readonly ?int $userId,
        public readonly int $batchId,
    ) {}

    /**
     * @template T
     *
     * @param  callable(): T  $load
     * @return T
     */
    public function remember(string $key, callable $load): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $load();
        }

        return $this->memo[$key];
    }

    public function forget(string $key): void
    {
        unset($this->memo[$key]);
    }
}
