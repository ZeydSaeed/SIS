<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Data\SchoolSnapshot;
use App\Domain\Organization\Repositories\SchoolRepositoryInterface;
use App\Domain\Organization\ValueObjects\SchoolStatus;
use Illuminate\Support\Facades\DB;

final class EloquentSchoolRepository implements SchoolRepositoryInterface
{
    private const UPDATABLE = ['directorate_id', 'name', 'address', 'phone', 'email'];

    public function find(int $schoolId): ?SchoolSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)
            ->first([
                'id',
                'directorate_id',
                'code',
                'name',
                'school_type',
                'address',
                'phone',
                'email',
                'status',
            ]);

        if ($row === null) {
            return null;
        }

        return new SchoolSnapshot(
            id: (int) $row->id,
            directorateId: (int) $row->directorate_id,
            code: (string) $row->code,
            name: (string) $row->name,
            schoolType: (int) $row->school_type,
            address: $row->address !== null ? (string) $row->address : null,
            phone: $row->phone !== null ? (string) $row->phone : null,
            email: $row->email !== null ? (string) $row->email : null,
            status: (int) $row->status,
        );
    }

    public function nextCode(): string
    {
        return OrganizationCodeSequence::next(SchemaHelper::qualified('organization', 'schools'), 'SCH');
    }

    public function directorateIsActive(int $directorateId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'directorates'))
            ->where('id', $directorateId)
            ->where('status', 1)
            ->exists();
    }

    public function create(
        int $directorateId,
        string $code,
        string $name,
        int $schoolType,
        ?string $address,
        ?string $phone,
        ?string $email,
        string $createdAt,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->insertGetId([
            'directorate_id' => $directorateId,
            'code' => $code,
            'name' => $name,
            'school_type' => $schoolType,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'status' => SchoolStatus::Active->value,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function update(int $schoolId, array $fields, string $updatedAt): bool
    {
        $payload = ['updated_at' => $updatedAt];
        foreach (self::UPDATABLE as $key) {
            if (array_key_exists($key, $fields)) {
                $payload[$key] = $fields[$key];
            }
        }

        return DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)
            ->update($payload) > 0;
    }

    public function setStatus(int $schoolId, int $status, string $updatedAt): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)
            ->update(['status' => $status, 'updated_at' => $updatedAt]) > 0;
    }
}
