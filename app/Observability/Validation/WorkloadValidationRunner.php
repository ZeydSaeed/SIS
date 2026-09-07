<?php

namespace App\Observability\Validation;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Models\User;
use App\Observability\Monitoring\HttpRequestTelemetryMonitor;
use Database\Seeders\SecurityPermissionSeeder;
use Database\Seeders\WorkloadValidationSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WorkloadValidationRunner
{
    private ?User $workloadUser = null;

    private ?string $workloadToken = null;

    private ?int $workloadSchoolId = null;

    public function __construct(
        private readonly HttpRequestTelemetryMonitor $telemetryMonitor,
        private readonly Kernel $kernel,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function runProfile(string $workload): ?array
    {
        $profile = config("sis.workload_validation.profiles.{$workload}");

        if (! is_array($profile)) {
            throw new RuntimeException("Unknown workload validation profile: {$workload}");
        }

        HttpRequestTelemetryMonitor::resetSamples();

        $studentIds = $this->studentIdsForProfile($workload);

        for ($iteration = 0; $iteration < (int) ($profile['warmup_requests'] ?? 0); $iteration++) {
            $this->dispatchStudentSearchRequest($profile, $studentIds, $iteration);
        }

        for ($iteration = 0; $iteration < (int) ($profile['sample_requests'] ?? 0); $iteration++) {
            $this->dispatchStudentSearchRequest($profile, $studentIds, $iteration);
        }

        return $this->telemetryMonitor->aggregateWorkload($workload);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function flushMetrics(string $workload): ?array
    {
        $snapshots = $this->telemetryMonitor->flush();

        $snapshot = $snapshots->first(
            fn ($item): bool => ($item->metrics['workload'] ?? null) === $workload,
        );

        return $snapshot?->metrics;
    }

    /**
     * @return list<int>
     */
    private function studentIdsForProfile(string $workload): array
    {
        if ($workload !== 'student_search') {
            return [];
        }

        return StudentRecord::query()
            ->orderBy('id')
            ->limit(10)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  list<int>  $studentIds
     */
    private function dispatchStudentSearchRequest(array $profile, array $studentIds, int $iteration): void
    {
        $terms = $profile['search_terms'] ?? ['Ali'];
        $term = (string) $terms[$iteration % max(1, count($terms))];

        match ($iteration % 3) {
            0 => $this->dispatch('GET', '/api/v1/students/search?q='.urlencode($term)),
            1 => $this->dispatch('GET', '/api/v1/students?page=1&per_page=20'),
            default => $this->dispatch(
                'GET',
                '/api/v1/students/'.($studentIds[$iteration % max(1, count($studentIds))] ?? 1),
            ),
        };
    }

    private function dispatch(string $method, string $uri): void
    {
        $request = Request::create($uri, $method, server: [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->workloadToken(),
            'HTTP_X_SCHOOL_ID' => (string) $this->workloadSchoolId(),
        ]);

        $response = null;

        try {
            $response = $this->kernel->handle($request);
        } finally {
            if ($response !== null) {
                $this->kernel->terminate($request, $response);
            }
        }
    }

    private function workloadUser(): User
    {
        if ($this->workloadUser !== null) {
            return $this->workloadUser;
        }

        $permissions = app(SecurityPermissionSeeder::class);
        $permissions->run();

        $this->workloadUser = User::query()->firstOrCreate(
            ['email' => 'workload-validation@sis.internal'],
            [
                'name' => 'Workload Validation',
                'password' => 'unused',
                'email_verified_at' => now(),
            ],
        );

        $permissions->assignRole($this->workloadUser, 'student_manager', $this->workloadSchoolId());

        return $this->workloadUser;
    }

    private function workloadToken(): string
    {
        if ($this->workloadToken !== null) {
            return $this->workloadToken;
        }

        $this->workloadToken = $this->workloadUser()->createToken('workload-validation')->plainTextToken;

        return $this->workloadToken;
    }

    private function workloadSchoolId(): int
    {
        if ($this->workloadSchoolId !== null) {
            return $this->workloadSchoolId;
        }

        $schools = SchemaHelper::qualified('organization', 'schools');
        $existing = DB::table($schools)->value('id');

        if ($existing !== null) {
            $this->workloadSchoolId = (int) $existing;

            return $this->workloadSchoolId;
        }

        $this->workloadSchoolId = app(WorkloadValidationSeeder::class)
            ->ensureWorkloadSchool();

        return $this->workloadSchoolId;
    }
}
