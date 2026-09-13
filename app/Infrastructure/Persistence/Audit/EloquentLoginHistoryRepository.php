<?php

namespace App\Infrastructure\Persistence\Audit;

use App\Database\SchemaHelper;
use App\Domain\Audit\Data\LoginHistorySnapshot;
use App\Domain\Audit\Repositories\LoginHistoryRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentLoginHistoryRepository implements LoginHistoryRepositoryInterface
{
    public function create(
        int $schoolId,
        int $userId,
        ?string $ipAddress,
        ?string $userAgent,
        int $loginStatus,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('audit', 'login_history'))->insertGetId([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'login_status' => $loginStatus,
            'created_at' => $createdAt,
        ]);
    }

    public function listForSchool(int $schoolId, ?int $userId, int $limit): array
    {
        $this->bindSchool($schoolId);

        $query = DB::table(SchemaHelper::qualified('audit', 'login_history'))
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->limit($limit);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->get([
            'id', 'school_id', 'user_id', 'ip_address', 'user_agent', 'login_status', 'created_at',
        ])->map(fn ($row): LoginHistorySnapshot => new LoginHistorySnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            userId: (int) $row->user_id,
            ipAddress: $row->ip_address !== null ? (string) $row->ip_address : null,
            userAgent: $row->user_agent !== null ? (string) $row->user_agent : null,
            loginStatus: (int) $row->login_status,
            createdAt: (string) $row->created_at,
        ))->all();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
