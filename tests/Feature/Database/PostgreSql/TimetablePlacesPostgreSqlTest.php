<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «الأماكن»: rooms and workshops of the timetable — create, edit, take out of service, and what protects them. */
final class TimetablePlacesPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $schoolId;

    private int $branchId;

    private function actAsManager(): void
    {
        $this->schoolId = $this->createSchool('SCH-PLC', 'Places school');
        $this->createAcademicYear();
        $this->actingAsTimetableManagerForSchool($this->schoolId);
        $this->branchId = (int) DB::table('organization.branches')->insertGetId([
            'school_id' => $this->schoolId, 'code' => 'BR-PLC', 'name' => 'Main', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function send(string $uri, array $payload, string $key): \Illuminate\Testing\TestResponse
    {
        return $this->from('/timetable')->withHeader('X-Idempotency-Key', $key)->post($uri, $payload);
    }

    #[Test]
    public function rooms_are_created_edited_and_refused_with_clear_codes(): void
    {
        $this->actAsManager();

        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'r1', 'name' => 'قاعة 1', 'capacity' => 40, 'room_type' => 1], 'pl-1')
            ->assertSessionHas('success', 'flash.timetable.engine.placeSaved');
        $room = DB::table('organization.rooms')->where('branch_id', $this->branchId)->first();
        $this->assertSame('R1', $room->code, 'codes are stored upper-case');
        $this->assertSame(40, (int) $room->capacity);

        // The same code again in the branch, a capacity of 0, an unknown type and a foreign branch are refused.
        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'R1', 'name' => 'x', 'room_type' => 1], 'pl-2')
            ->assertSessionHasErrors(['engine' => 'timetable.place_code_taken']);
        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'R2', 'name' => 'x', 'capacity' => 0, 'room_type' => 1], 'pl-3')
            ->assertSessionHasErrors(['capacity']);
        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'R2', 'name' => 'x', 'room_type' => 9], 'pl-4')
            ->assertSessionHasErrors(['room_type']);
        $this->send('/timetable/places/rooms', ['branch_id' => 999999, 'code' => 'R2', 'name' => 'x', 'room_type' => 1], 'pl-5')
            ->assertSessionHasErrors(['engine' => 'timetable.place_branch_invalid']);

        // Edit: name, capacity, type change; code and branch stay.
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'pl-6')
            ->patch('/timetable/places/rooms/'.$room->id, ['name' => 'مختبر', 'capacity' => 20, 'room_type' => 2])
            ->assertSessionHas('success', 'flash.timetable.engine.placeSaved');
        $room = DB::table('organization.rooms')->where('id', $room->id)->first();
        $this->assertSame(['R1', 'مختبر', 20, 2], [$room->code, $room->name, (int) $room->capacity, (int) $room->room_type]);

        // The page hands the sheet every place with its usage.
        $yearId = (int) DB::table('academic.academic_years')->where('is_current', true)->value('id');
        $this->get('/timetable?academic_year_id='.$yearId)->assertInertia(fn ($page) => $page
            ->where('engine.places.rooms.0.code', 'R1')
            ->where('engine.places.rooms.0.branch_name', 'Main')
            ->where('engine.places.rooms.0.used', 0)
            ->has('engine.places.workshops', 0)
            ->etc());
    }

    #[Test]
    public function room_names_are_limited_to_100_characters(): void
    {
        $this->actAsManager();

        // Exactly 100 characters: accepted.
        $name100 = str_repeat('ع', 100); // 100 Arabic chars
        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'r-100', 'name' => $name100, 'capacity' => 30, 'room_type' => 1], 'pl-name-100')
            ->assertSessionHas('success', 'flash.timetable.engine.placeSaved');

        // 101 characters: rejected.
        $name101 = str_repeat('ع', 101);
        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'r-101', 'name' => $name101, 'room_type' => 1], 'pl-name-101')
            ->assertSessionHasErrors(['engine' => 'timetable.place_name_invalid']);
    }

    #[Test]
    public function workshop_names_are_limited_to_100_characters(): void
    {
        $this->actAsManager();

        // Exactly 100 characters: accepted.
        $name100 = str_repeat('ع', 100);
        $this->send('/timetable/places/workshops', ['code' => 'ws-100', 'name' => $name100, 'capacity' => 20, 'safety_capacity' => 16], 'pw-name-100')
            ->assertSessionHas('success', 'flash.timetable.engine.placeSaved');

        // 101 characters: rejected.
        $name101 = str_repeat('ع', 101);
        $this->send('/timetable/places/workshops', ['code' => 'ws-101', 'name' => $name101, 'capacity' => 20, 'safety_capacity' => 16], 'pw-name-101')
            ->assertSessionHasErrors(['engine' => 'timetable.place_name_invalid']);
    }

    #[Test]
    public function workshops_validate_safety_capacity_and_room(): void
    {
        $this->actAsManager();
        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'LAB1', 'name' => 'مختبر', 'capacity' => 20, 'room_type' => 2], 'pw-1');
        $roomId = (int) DB::table('organization.rooms')->where('code', 'LAB1')->value('id');

        $this->send('/timetable/places/workshops', ['code' => 'ws1', 'name' => 'ورشة', 'capacity' => 20, 'safety_capacity' => 25, 'room_id' => $roomId], 'pw-2')
            ->assertSessionHasErrors(['safety_capacity']);
        $this->send('/timetable/places/workshops', ['code' => 'ws1', 'name' => 'ورشة', 'capacity' => 20, 'safety_capacity' => 16, 'room_id' => 999999], 'pw-3')
            ->assertSessionHasErrors(['engine' => 'timetable.workshop_room_invalid']);
        $this->send('/timetable/places/workshops', ['code' => 'ws1', 'name' => 'ورشة', 'capacity' => 20, 'safety_capacity' => 16, 'room_id' => $roomId], 'pw-4')
            ->assertSessionHas('success', 'flash.timetable.engine.placeSaved');
        $this->send('/timetable/places/workshops', ['code' => 'WS1', 'name' => 'ورشة ثانية', 'capacity' => 20, 'safety_capacity' => 16], 'pw-5')
            ->assertSessionHasErrors(['engine' => 'timetable.place_code_taken']);

        $workshopId = (int) DB::table('vocational.workshops')->where('code', 'WS1')->value('id');
        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'pw-6')
            ->patch('/timetable/places/workshops/'.$workshopId, ['name' => 'ورشة معدّلة', 'capacity' => 24, 'safety_capacity' => 20])
            ->assertSessionHas('success', 'flash.timetable.engine.placeSaved');
        $this->assertSame(24, (int) DB::table('vocational.workshops')->where('id', $workshopId)->value('capacity'));
    }

    #[Test]
    public function a_place_in_use_cannot_be_taken_out_of_service_but_an_idle_one_can(): void
    {
        $this->actAsManager();
        $this->send('/timetable/places/rooms', ['branch_id' => $this->branchId, 'code' => 'R1', 'name' => 'قاعة', 'capacity' => 30, 'room_type' => 1], 'pu-1');
        $roomId = (int) DB::table('organization.rooms')->where('code', 'R1')->value('id');

        // A workshop housed in the room makes the room "in use".
        $this->send('/timetable/places/workshops', ['code' => 'WS9', 'name' => 'ورشة', 'capacity' => 10, 'safety_capacity' => 8, 'room_id' => $roomId], 'pu-2');
        $this->send('/timetable/places/status', ['kind' => 'room', 'id' => $roomId, 'active' => 0], 'pu-3')
            ->assertSessionHasErrors(['engine' => 'timetable.place_in_use']);
        $this->assertSame(1, (int) DB::table('organization.rooms')->where('id', $roomId)->value('status'));

        $workshopId = (int) DB::table('vocational.workshops')->where('code', 'WS9')->value('id');
        $this->send('/timetable/places/status', ['kind' => 'workshop', 'id' => $workshopId, 'active' => 0], 'pu-4')
            ->assertSessionHas('success', 'flash.timetable.engine.placeDisabled');
        $this->send('/timetable/places/status', ['kind' => 'room', 'id' => $roomId, 'active' => 0], 'pu-5')
            ->assertSessionHas('success', 'flash.timetable.engine.placeDisabled');
        $this->send('/timetable/places/status', ['kind' => 'room', 'id' => $roomId, 'active' => 1], 'pu-6')
            ->assertSessionHas('success', 'flash.timetable.engine.placeEnabled');
        $this->send('/timetable/places/status', ['kind' => 'room', 'id' => 999999, 'active' => 0], 'pu-7')
            ->assertSessionHasErrors(['engine' => 'timetable.room_not_found']);
    }

    #[Test]
    public function a_room_of_another_school_cannot_be_touched(): void
    {
        $this->actAsManager();
        $otherSchool = $this->createSchool('SCH-PLC-2', 'Other school');
        $otherBranch = (int) DB::table('organization.branches')->insertGetId([
            'school_id' => $otherSchool, 'code' => 'BR-O', 'name' => 'Other', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreign = (int) DB::table('organization.rooms')->insertGetId([
            'branch_id' => $otherBranch, 'code' => 'X1', 'name' => 'Foreign', 'room_type' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'px-1')
            ->patch('/timetable/places/rooms/'.$foreign, ['name' => 'hijack', 'room_type' => 1])
            ->assertSessionHasErrors(['engine' => 'timetable.room_not_found']);
        $this->send('/timetable/places/status', ['kind' => 'room', 'id' => $foreign, 'active' => 0], 'px-2')
            ->assertSessionHasErrors(['engine' => 'timetable.room_not_found']);
        $this->send('/timetable/places/rooms', ['branch_id' => $otherBranch, 'code' => 'Z', 'name' => 'x', 'room_type' => 1], 'px-3')
            ->assertSessionHasErrors(['engine' => 'timetable.place_branch_invalid']);
        $this->assertSame('Foreign', DB::table('organization.rooms')->where('id', $foreign)->value('name'));
    }

    #[Test]
    public function a_viewer_cannot_manage_places(): void
    {
        $schoolId = $this->createSchool('SCH-PLC-3', 'Viewer school');
        $this->actingAsAuthenticatedWithoutPermissions();
        $this->withSession(['current_school_id' => $schoolId]);

        $this->from('/timetable')->withHeader('X-Idempotency-Key', 'pv-1')
            ->post('/timetable/places/rooms', ['branch_id' => 1, 'code' => 'A', 'name' => 'x', 'room_type' => 1])
            ->assertForbidden();
    }
}
