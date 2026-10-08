<?php

namespace Database\Seeders;

use App\Application\Vocational\Commands\CreateWorkshopCommand;
use App\Application\Vocational\Commands\CreateWorkshopHandler;
use App\Database\SchemaHelper;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Places for the timetable: two classrooms (room_type 1) and one practical room (room_type 2, capacity 20) per
 * branch of the demo school, and a workshop (through the real handler) in every practical room. Idempotent —
 * rooms are keyed by (branch, code) and workshops by their idempotency key.
 */
final class RoomsAndWorkshopsSeeder extends Seeder
{
    public const CLASSROOM = 1;

    public const PRACTICAL = 2;

    public function run(): void
    {
        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)->value('id');
        if ($schoolId < 1) {
            throw new RuntimeException('RoomsAndWorkshopsSeeder needs the demo school.');
        }
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);

        $rooms = SchemaHelper::qualified('organization', 'rooms');
        $branches = DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $schoolId)->where('status', 1)->orderBy('id')->get(['id', 'name']);

        foreach ($branches as $i => $branch) {
            $n = $i + 1;
            $defs = [
                ["R{$n}-1", "قاعة {$n}-1", self::CLASSROOM, 40],
                ["R{$n}-2", "قاعة {$n}-2", self::CLASSROOM, 40],
                ["LAB{$n}", 'ورشة وتدريب '.$branch->name, self::PRACTICAL, 20],
            ];
            foreach ($defs as [$code, $name, $type, $capacity]) {
                $exists = DB::table($rooms)->where('branch_id', $branch->id)->where('code', $code)->exists();
                if (! $exists) {
                    DB::table($rooms)->insert([
                        'branch_id' => $branch->id, 'code' => $code, 'name' => $name, 'capacity' => $capacity,
                        'room_type' => $type, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            $labId = (int) DB::table($rooms)->where('branch_id', $branch->id)->where('code', "LAB{$n}")->value('id');
            $result = app(CreateWorkshopHandler::class)->handle(new CreateWorkshopCommand(
                schoolId: $schoolId,
                code: "WS-{$n}",
                name: 'ورشة '.$branch->name,
                capacity: 20,
                safetyCapacity: 16,
                roomId: $labId,
                idempotencyKey: 'seed-rooms:workshop:'.$n,
            ));
            if ($result->failed() && ! in_array('vocational.workshop_code_exists', $result->errors, true)) {
                throw new RuntimeException('Workshop WS-'.$n.' failed: '.implode(', ', $result->errors));
            }
        }
    }
}
