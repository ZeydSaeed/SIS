<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class RoomCatalogueSortPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function rooms_page_sorts_by_practical_and_weekly_lessons(): void
    {
        $schoolId = $this->createSchool('SCH-ROOM-SORT', 'Rooms Sort');
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR1',
            'name' => 'Main Branch',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $theory = $this->room($branchId, 'R1', false);
        $lab = $this->room($branchId, 'R2', true);

        $repository = $this->app->make(RoomCatalogueRepositoryInterface::class);

        $practicalDesc = $repository->page($schoolId, [], 'practical', 'desc', 1, 10);
        self::assertSame([$lab, $theory], array_column($practicalDesc['rows'], 'id'));

        $practicalAsc = $repository->page($schoolId, [], 'practical', 'asc', 1, 10);
        self::assertSame([$theory, $lab], array_column($practicalAsc['rows'], 'id'));

        // No schedules: every room has 0 weekly lessons, ties fall back to the id order.
        $lessons = $repository->page($schoolId, [], 'weekly_lessons', 'desc', 1, 10);
        self::assertSame([$theory, $lab], array_column($lessons['rows'], 'id'));
        self::assertSame([0, 0], array_column($lessons['rows'], 'weekly_lessons'));
    }

    private function room(int $branchId, string $code, bool $practical): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId([
            'branch_id' => $branchId,
            'code' => $code,
            'name' => 'Room '.$code,
            'capacity' => 30,
            'room_type' => 1,
            'supports_practical' => $practical,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
