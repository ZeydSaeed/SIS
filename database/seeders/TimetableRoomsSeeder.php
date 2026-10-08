<?php

namespace Database\Seeders;

use App\Database\SchemaHelper;
use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts the demo lessons into places: every section gets its own classroom for theory lessons, and a practical
 * lesson (subject type «عملي») goes to the practical room of the section's main branch when that room is free in
 * the slot. Rooms stay unique per slot, so the timetable audit gains no room clash.
 *
 * Run after {@see RoomsAndWorkshopsSeeder} and {@see TimetableScenarioSeeder}; idempotent (it only fills lessons
 * without a room).
 */
final class TimetableRoomsSeeder extends Seeder
{
    public function run(): void
    {
        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('code', FoundationReference::SCHOOL_CODE)->value('id');
        $yearId = (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('is_current', true)->value('id');
        if ($schoolId < 1 || $yearId < 1) {
            return;
        }
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);

        $rooms = SchemaHelper::qualified('organization', 'rooms');
        $schedules = SchemaHelper::qualified('timetable', 'schedules');
        $classrooms = DB::table($rooms.' as r')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)->where('r.room_type', RoomsAndWorkshopsSeeder::CLASSROOM)->where('r.status', 1)
            ->orderBy('r.id')->pluck('r.id')->map(fn ($id): int => (int) $id)->all();
        $practicalByBranch = DB::table($rooms.' as r')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)->where('r.room_type', RoomsAndWorkshopsSeeder::PRACTICAL)->where('r.status', 1)
            ->pluck('r.id', 'r.branch_id')->map(fn ($id): int => (int) $id)->all();

        $sectionIds = DB::table($schedules)->where('school_id', $schoolId)->where('academic_year_id', $yearId)
            ->where('lifecycle_status', 1)->distinct()->orderBy('section_id')->pluck('section_id')->map(fn ($id): int => (int) $id)->all();
        $practical = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->where('subject_type', 3)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($sectionIds as $i => $sectionId) {
            $classroom = $classrooms[$i % max(1, count($classrooms))] ?? null;
            $branchId = DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
                ->where('section_id', $sectionId)->where('status', 1)->whereNotNull('branch_id')
                ->groupBy('branch_id')->orderByRaw('count(*) desc')->value('branch_id');
            $lab = $branchId !== null ? ($practicalByBranch[(int) $branchId] ?? null) : null;

            foreach (DB::table($schedules)->where('school_id', $schoolId)->where('academic_year_id', $yearId)
                ->where('section_id', $sectionId)->where('lifecycle_status', 1)->whereNull('room_id')->get(['id', 'day_of_week', 'period_id', 'subject_id']) as $lesson) {
                $room = in_array((int) $lesson->subject_id, $practical, true) ? $lab : $classroom;
                if ($room === null) {
                    continue;
                }
                $taken = DB::table($schedules)->where('school_id', $schoolId)->where('lifecycle_status', 1)
                    ->where('day_of_week', $lesson->day_of_week)->where('period_id', $lesson->period_id)->where('room_id', $room)->exists();
                if (! $taken) {
                    DB::table($schedules)->where('id', $lesson->id)->update(['room_id' => $room, 'updated_at' => now()]);
                }
            }
        }
    }
}
