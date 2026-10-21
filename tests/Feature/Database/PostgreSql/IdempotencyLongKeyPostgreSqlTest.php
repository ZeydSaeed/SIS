<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Infrastructure\Persistence\Idempotency\EloquentIdempotencyStore;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * audit.idempotency_keys.key is VARCHAR(64). A longer X-Idempotency-Key (the follow-up page builds one from every
 * selected application id) used to commit the write and then fail with HTTP 500 while storing the key — and a
 * retry wrote again. The store now keeps a fixed-width digest of a long key: the write succeeds once, a retry
 * replays it.
 */
final class IdempotencyLongKeyPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_write_with_a_key_longer_than_the_column_succeeds_once_and_replays_on_retry(): void
    {
        $schoolId = $this->createSchool('SCH-IDEM-LONG', 'Idempotency long key');
        $yearId = $this->createAcademicYear();
        $this->actingAsTeachersManagerForSchool($schoolId);
        $key = 'follow-up-6-'.implode('-', range(1001, 1030)).'-1760995200000';
        $this->assertGreaterThan(64, strlen($key));

        $payload = ['academic_year_id' => $yearId, 'employee_code' => 'EMP-IDEM-1', 'first_name' => 'Ali', 'last_name' => 'Hassan'];
        foreach ([1, 2] as $attempt) {
            $this->from('/teachers')
                ->withHeader('X-Idempotency-Key', $key)
                ->post('/teachers', $payload)
                ->assertRedirect('/teachers')
                ->assertSessionHas('success', 'flash.teachers.registered');
        }

        $this->assertSame(1, DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('employee_code', 'EMP-IDEM-1')->count());
        $this->assertTrue(DB::table(SchemaHelper::qualified('audit', 'idempotency_keys'))
            ->where('key', EloquentIdempotencyStore::column($key))->exists());
    }

    #[Test]
    public function keys_within_the_column_are_stored_as_sent_and_long_ones_map_to_one_fixed_width_digest(): void
    {
        $this->assertSame('ui-teach-w-1', EloquentIdempotencyStore::column('ui-teach-w-1'));
        $exact = str_repeat('k', 64);
        $this->assertSame($exact, EloquentIdempotencyStore::column($exact));

        $long = str_repeat('k', 65);
        $digest = EloquentIdempotencyStore::column($long);
        $this->assertSame(64, strlen($digest));
        $this->assertSame($digest, EloquentIdempotencyStore::column($long));
        $this->assertNotSame($digest, EloquentIdempotencyStore::column($long.'x'));

        $store = new EloquentIdempotencyStore;
        $store->store($long, 'IdempotencyLongKeyProbe', ['id' => 7]);
        $this->assertSame(['id' => 7], $store->find($long, 'IdempotencyLongKeyProbe'));
        $this->assertNull($store->find($long.'x', 'IdempotencyLongKeyProbe'));
    }
}
