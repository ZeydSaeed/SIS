<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\SchoolScopedRunnerInterface;
use App\Security\Context\SchoolContextScope;
use Illuminate\Support\Facades\DB;

final class SchoolContextScopeRunner implements SchoolScopedRunnerInterface
{
    public function __construct(
        private readonly SchoolContextScope $scope,
    ) {}

    public function run(int $schoolId, callable $callback): mixed
    {
        return $this->scope->run($schoolId, $callback);
    }

    public function schoolNames(array $schoolIds): array
    {
        return DB::table('organization.schools')->whereIn('id', $schoolIds)->pluck('name', 'id')
            ->mapWithKeys(static fn ($name, $id): array => [(int) $id => (string) $name])->all();
    }
}
