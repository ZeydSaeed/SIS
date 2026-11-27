<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «حذف آمن» — classes / sections, rooms / room types, schools / directorates. */
final class SafeDeleteStructurePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $schoolId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->schoolId = $this->createSchool('SCH-SDS', 'Structure delete');
        $user = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->grantSchoolManager($user, $this->schoolId);
        $seeder->grantEnrollmentManager($user, $this->schoolId);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $this->schoolId]);
    }

    #[Test]
    public function a_class_goes_with_its_unused_sections_and_a_used_section_is_refused(): void
    {
        $yearId = $this->createAcademicYear('AY-SDS');
        $used = $this->createClassForSchool($this->schoolId, $yearId);
        $usedSection = $this->createSectionForClass((int) $used->id);
        $student = $this->createStudentForSchool($this->schoolId);
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $this->schoolId]);
        DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insert([
            'student_id' => $student->id, 'academic_year_id' => $yearId, 'school_id' => $this->schoolId,
            'class_id' => $used->id, 'section_id' => $usedSection->id, 'status' => 1, 'effective_from' => '2026-09-01',
        ]);

        $this->send('post', '/organization/sections/'.$usedSection->id.'/delete')
            ->assertSessionHasErrors(['section' => 'delete.linked?with='.rawurlencode('التسجيل في الصفوف')]);
        $this->send('post', '/organization/classes/'.$used->id.'/delete')
            ->assertSessionHasErrors(['class' => 'delete.linked?with='.rawurlencode('التسجيل في الصفوف')]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'sections'), ['id' => $usedSection->id]);

        $empty = $this->createClassForSchool($this->schoolId, $yearId);
        $emptySection = $this->createSectionForClass((int) $empty->id);
        $this->send('post', '/organization/classes/'.$empty->id.'/delete')->assertSessionHas('success', 'flash.structure.classDeleted');
        $this->assertDatabaseMissing(SchemaHelper::qualified('enrollment', 'classes'), ['id' => $empty->id]);
        $this->assertDatabaseMissing(SchemaHelper::qualified('enrollment', 'sections'), ['id' => $emptySection->id]);
    }

    #[Test]
    public function a_room_type_in_use_is_refused_until_its_rooms_are_deleted(): void
    {
        $branch = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId(['school_id' => $this->schoolId, 'code' => 'BR-SDS', 'name' => 'Branch', 'status' => 1]);
        $type = (int) DB::table(SchemaHelper::qualified('organization', 'room_types'))->insertGetId(['school_id' => $this->schoolId, 'code' => 'LAB-SDS', 'name' => 'Lab', 'kind' => 2, 'status' => 1]);
        $room = (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId(['branch_id' => $branch, 'code' => 'R-SDS', 'name' => 'Room', 'room_type' => 2, 'room_type_id' => $type, 'status' => 1]);

        $this->send('post', '/organization/room-types/'.$type.'/delete')
            ->assertSessionHasErrors(['room' => 'delete.linked?with='.rawurlencode('الغرف')]);
        $this->send('post', '/organization/rooms/'.$room.'/delete')->assertSessionHas('success', 'flash.rooms.roomDeleted');
        $this->send('post', '/organization/room-types/'.$type.'/delete')->assertSessionHas('success', 'flash.rooms.typeDeleted');
        $this->assertDatabaseMissing(SchemaHelper::qualified('organization', 'room_types'), ['id' => $type]);
    }

    #[Test]
    public function the_current_school_and_a_directorate_with_schools_are_refused_an_empty_school_is_deleted(): void
    {
        $directorate = $this->directorateOfSchool($this->schoolId);

        $this->send('post', '/organization/schools/'.$this->schoolId.'/delete')
            ->assertSessionHasErrors(['school' => 'delete.linked?with='.rawurlencode('المدرسة التي تعمل عليها الآن — بدّل إلى مدرسة أخرى أولاً')]);
        $this->send('post', '/organization/directorates/'.$directorate.'/delete')
            ->assertSessionHasErrors(['directorate' => 'delete.linked?with='.rawurlencode('المدارس')]);

        // Added from the page (its creator is linked to it); empty, it is deleted with that access row.
        $this->send('post', '/organization/schools', ['name' => 'اعدادية فارغة', 'directorate_id' => $directorate, 'status' => 1])->assertSessionHasNoErrors();
        $empty = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->where('name', 'اعدادية فارغة')->value('id');
        $this->app->forgetInstance(\App\Security\Authorization\SchoolScopeService::class);
        $this->send('post', '/organization/schools/'.$empty.'/delete')->assertSessionHas('success', 'flash.organization.schoolDeleted');
        $this->assertDatabaseMissing(SchemaHelper::qualified('organization', 'schools'), ['id' => $empty]);
        $this->assertDatabaseMissing(SchemaHelper::qualified('security', 'user_roles'), ['school_id' => $empty]);
    }

    private function send(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->withHeader('X-Idempotency-Key', 'sds-'.bin2hex(random_bytes(6)))->{$method}($uri, $data);
    }
}
