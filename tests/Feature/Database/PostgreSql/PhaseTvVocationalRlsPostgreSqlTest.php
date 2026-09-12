<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

final class PhaseTvVocationalRlsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function vocational_tables_have_force_rls_and_reject_delete(): void
    {
        foreach (['specializations', 'tracks', 'specialization_subjects'] as $table) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'vocational' AND c.relname = ?
            ", [$table]);
            $this->assertNotNull($row, $table);
            $this->assertTrue((bool) $row->rls, $table);
            $this->assertTrue((bool) $row->force_rls, $table);
        }

        $trigger = DB::selectOne("
            SELECT 1 AS ok FROM pg_trigger t
            JOIN pg_class c ON c.oid = t.tgrelid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'vocational' AND c.relname = 'specializations'
              AND t.tgname = 'specializations_reject_delete' AND NOT t.tgisinternal
        ");
        $this->assertNotNull($trigger);
    }

    #[Test]
    public function vocational_rls_isolates_specializations_and_child_rows(): void
    {
        $schoolA = $this->createSchool('SCH-TV-V05A', 'Voc A');
        $schoolB = $this->createSchool('SCH-TV-V05B', 'Voc B');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
        $specA = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolA,
            'code' => 'VA',
            'name' => 'Spec A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $trackA = (int) DB::table(SchemaHelper::qualified('vocational', 'tracks'))->insertGetId([
            'specialization_id' => $specA,
            'code' => 'TA',
            'name' => 'Track A',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolB]);
        $specB = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolB,
            'code' => 'VB',
            'name' => 'Spec B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('vocational', 'tracks'))->insert([
            'specialization_id' => $specB,
            'code' => 'TB',
            'name' => 'Track B',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        PostgreSqlRlsActor::become();
        try {
            DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolA]);
            $specs = collect(DB::select('SELECT id FROM vocational.specializations'))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $tracks = collect(DB::select('SELECT id FROM vocational.tracks'))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();

            $this->assertSame([$specA], $specs);
            $this->assertSame([$trackA], $tracks);
            $this->assertNotContains($specB, $specs);

            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::table(SchemaHelper::qualified('vocational', 'specializations'))
                ->where('id', $specA)
                ->delete();
        } finally {
            PostgreSqlRlsActor::reset();
        }
    }
}
