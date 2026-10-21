<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Infrastructure\Persistence\Idempotency\EloquentIdempotencyStore;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * Laravel writes timestamps without an offset; with the database session in another zone than the application
 * (the local server runs Asia/Baghdad, the app UTC) every app-written TIMESTAMPTZ was stored hours off — found by
 * the e2e suite. The connection now speaks the application's zone, so a written instant comes back unchanged.
 */
final class DatabaseSessionTimezonePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    #[Test]
    public function the_session_uses_the_application_time_zone(): void
    {
        $this->assertSame(config('app.timezone'), DB::selectOne('SHOW timezone')->TimeZone);
    }

    #[Test]
    public function an_app_written_timestamp_is_stored_as_the_same_instant(): void
    {
        $before = time();
        (new EloquentIdempotencyStore)->store('tz-probe', 'TimezoneProbe', ['ok' => true]);
        $stored = (int) DB::selectOne("SELECT extract(epoch FROM created_at)::bigint AS t FROM audit.idempotency_keys WHERE key = 'tz-probe'")->t;

        $this->assertEqualsWithDelta($before, $stored, 5, 'created_at must be the instant it was written, not shifted by the server zone');
        // And the database's own clock agrees with the application's.
        $this->assertEqualsWithDelta(time(), (int) DB::selectOne('SELECT extract(epoch FROM now())::bigint AS t')->t, 5);
    }
}
