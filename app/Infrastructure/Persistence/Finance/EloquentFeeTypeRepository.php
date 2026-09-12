<?php

namespace App\Infrastructure\Persistence\Finance;

use App\Database\SchemaHelper;
use App\Domain\Finance\Data\FeeTypeSnapshot;
use App\Domain\Finance\Repositories\FeeTypeRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentFeeTypeRepository implements FeeTypeRepositoryInterface
{
    public function create(
        int $schoolId,
        string $code,
        string $name,
        string $amount,
        bool $isRecurring,
        int $status,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('finance', 'fee_types'))->insertGetId([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'amount' => $amount,
            'is_recurring' => $isRecurring,
            'status' => $status,
            'created_at' => $createdAt,
        ]);
    }

    public function findIdBySchoolAndCode(int $schoolId, string $code): ?int
    {
        $this->bindSchool($schoolId);

        $id = DB::table(SchemaHelper::qualified('finance', 'fee_types'))
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function listBySchool(int $schoolId, ?int $status = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('finance', 'fee_types'))
            ->where('school_id', $schoolId)
            ->orderBy('code');

        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get([
            'id',
            'school_id',
            'code',
            'name',
            'amount',
            'is_recurring',
            'status',
            'created_at',
        ])->map(static function (object $row): FeeTypeSnapshot {
            return new FeeTypeSnapshot(
                id: (int) $row->id,
                schoolId: (int) $row->school_id,
                code: (string) $row->code,
                name: (string) $row->name,
                amount: (string) $row->amount,
                isRecurring: (bool) $row->is_recurring,
                status: (int) $row->status,
                createdAt: (string) $row->created_at,
            );
        })->all();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
