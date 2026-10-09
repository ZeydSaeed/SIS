<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Timetable\Queries\TestTimetableHandler;
use App\Application\Timetable\Queries\TestTimetableQuery;
use App\Database\SchemaHelper;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/**
 * The timetable workbench on PostgreSQL: «الغرف الدراسية» (rooms + managed types), «الاختصار واللون» written by each
 * owning page and read by the timetable, the school day (insert / move / resize / remove a break with re-timing),
 * «تنسيق الجدول», «اختبار الجدول» (report on demand + ignore / review marks), and «استيراد Excel»
 * (template → upload → preview with errors → commit → report).
 */
final class TimetableWorkbenchPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $key = 0;

    #[Test]
    public function rooms_are_managed_on_their_own_page_with_types_details_and_appearance(): void
    {
        $ctx = $this->seedSchool('WB-R');

        $this->get('/organization/rooms')->assertOk()->assertInertia(fn ($page) => $page
            ->component('organization/rooms')
            ->where('authorization.can_manage', true)
            ->has('types', 9)
            ->etc());

        $labType = (int) DB::table('organization.room_types')->whereNull('school_id')->where('code', 'COMPUTER_LAB')->value('id');
        $this->write('/organization/rooms', [
            'branch_id' => $ctx['branch'], 'code' => 'lab-1', 'name' => 'مختبر الحاسوب', 'room_number' => '12', 'room_type_id' => $labType,
            'capacity' => 30, 'building' => 'أ', 'floor' => 1, 'supports_practical' => true, 'equipment' => '20 حاسوب', 'abbreviation' => 'م.ح', 'color_hue' => 190,
        ])->assertSessionHas('success', 'flash.rooms.roomCreated');
        $room = DB::table('organization.rooms')->where('branch_id', $ctx['branch'])->where('code', 'LAB-1')->first();
        $this->assertNotNull($room);
        $this->assertSame(2, (int) $room->room_type, 'practical support keeps the solver class');
        $this->assertSame('م.ح', $room->abbreviation);

        // Same code in the branch, a type of another school, a department of another branch: refused.
        $this->write('/organization/rooms', ['branch_id' => $ctx['branch'], 'code' => 'LAB-1', 'name' => 'x', 'supports_practical' => false])
            ->assertSessionHasErrors(['room' => 'organization.room_code_taken']);
        $this->patchWrite('/organization/rooms/'.$room->id, ['name' => 'مختبر', 'supports_practical' => false, 'floor' => 300])->assertSessionHasErrors('floor');

        // A school type; system types are read-only.
        $this->write('/organization/room-types', ['code' => 'ELEC-WS', 'name' => 'ورشة كهرباء', 'kind' => 3])->assertSessionHas('success', 'flash.rooms.typeCreated');
        $this->patchWrite('/organization/room-types/'.$labType, ['name' => 'x', 'kind' => 2])->assertSessionHasErrors(['room_type' => 'organization.room_type_system']);

        // Appearance through the organization endpoint; the timetable reads it from the owner.
        $this->patchWrite('/organization/appearance', ['target' => 'room', 'id' => $room->id, 'abbreviation' => 'حاسوب', 'color_hue' => 205])
            ->assertSessionHas('success', 'flash.appearance.updated');
        $this->patchWrite('/organization/appearance', ['target' => 'room', 'id' => $room->id, 'color_hue' => 400])->assertSessionHasErrors('color_hue');

        // Out of service, then back; the list filters by state.
        $this->write('/organization/rooms/'.$room->id.'/status', ['active' => 0])->assertSessionHas('success', 'flash.rooms.roomDeactivated');
        $this->get('/organization/rooms?state=2')->assertInertia(fn ($page) => $page->has('rooms', 1)->where('rooms.0.code', 'LAB-1')->etc());
        $this->write('/organization/rooms/'.$room->id.'/status', ['active' => 1])->assertSessionHas('success', 'flash.rooms.roomReactivated');

        $this->get('/timetable?academic_year_id='.$ctx['year'])->assertInertia(fn ($page) => $page
            ->where('display.rooms.'.$room->id.'.short', 'حاسوب')
            ->where('display.rooms.'.$room->id.'.color_hue', 205)
            ->where('display.settings.layout', 'corner')
            ->etc());
    }

    #[Test]
    public function each_owner_page_writes_its_abbreviation_and_colour_and_the_timetable_reads_them(): void
    {
        $ctx = $this->seedSchool('WB-A');

        // Subject (curriculum page): the partial update accepts the display fields.
        $this->patchWrite('/curriculum/subjects/'.$ctx['theory'], ['abbreviation' => '  ك.ن ', 'color_hue' => 30])->assertSessionHas('success', 'flash.curriculum.subjectUpdated');
        $this->assertSame('ك.ن', DB::table('curriculum.subjects')->where('id', $ctx['theory'])->value('abbreviation'));

        // Teacher (teachers page): appearance endpoint + academic title in the profile.
        $title = (int) DB::table('teachers.academic_titles')->where('code', 'ENGINEER')->value('id');
        $this->patchWrite('/teachers/'.$ctx['theoryTeacher'].'/appearance', ['abbreviation' => 'علي ح', 'color_hue' => 120])->assertSessionHas('success', 'flash.appearance.updated');
        $this->patchWrite('/teachers/'.$ctx['theoryTeacher'], ['first_name' => 'علي', 'last_name' => 'حسن', 'academic_title_id' => $title, 'abbreviation' => 'علي'])
            ->assertSessionHas('success', 'flash.teachers.updated');
        $this->patchWrite('/teachers/'.$ctx['theoryTeacher'], ['first_name' => 'علي', 'last_name' => 'حسن', 'academic_title_id' => 999])
            ->assertSessionHasErrors(['teacher' => 'teachers.academic_title_invalid']);

        // Section (classes & sections page).
        $this->patchWrite('/organization/classes-sections/appearance', ['target' => 'section', 'id' => $ctx['sectionA'], 'abbreviation' => 'A', 'color_hue' => 250])
            ->assertSessionHas('success', 'flash.structure.appearanceUpdated');

        $this->get('/timetable?academic_year_id='.$ctx['year'])->assertInertia(fn ($page) => $page
            ->where('display.subjects.'.$ctx['theory'].'.short', 'ك.ن')
            ->where('display.subjects.'.$ctx['theory'].'.color_hue', 30)
            ->where('display.teachers.'.$ctx['theoryTeacher'].'.short', 'علي')
            ->where('display.teachers.'.$ctx['theoryTeacher'].'.title', 'مهندس')
            ->where('display.teachers.'.$ctx['practicalTeacher'].'.suggested', 'سعيد')
            ->where('display.sections.'.$ctx['sectionA'].'.short', 'A')
            ->etc());
    }

    #[Test]
    public function breaks_are_inserted_moved_resized_and_removed_with_the_day_re_timed(): void
    {
        $ctx = $this->seedSchool('WB-D');
        [$p1, $p2, $p3, $p4] = $ctx['periods'];

        $this->write('/timetable/periods/reshape', ['operation' => 'insert_break', 'after_period_id' => $p2, 'minutes' => 15, 'name' => 'استراحة الطلاب بعد الحصة الثانية', 'show_in' => 5, 'print_in' => 31])
            ->assertSessionHas('success', 'flash.timetable.dayReshaped');
        $day = $this->day($ctx['school']);
        $this->assertSame(['08:00', '08:45', '09:30', '09:45', '10:30'], array_column($day, 'start'));
        $break = $day[2];
        $this->assertSame(2, $break['type']);
        $this->assertSame('استراحة الطلاب بعد الحصة الثانية', $break['name']);
        $this->assertSame(5, $break['show_in']);
        $this->assertSame([$p1, $p2, $break['id'], $p3, $p4], array_column($day, 'id'), 'lesson periods keep their ids');

        // Move after the third lesson, resize to 20 minutes, then remove: the day closes behind it.
        $this->write('/timetable/periods/reshape', ['operation' => 'move_break', 'period_id' => $break['id'], 'after_period_id' => $p3])->assertSessionHasNoErrors();
        $this->assertSame(['08:00', '08:45', '09:30', '10:15', '10:30'], array_column($this->day($ctx['school']), 'start'));
        $this->write('/timetable/periods/reshape', ['operation' => 'resize', 'period_id' => $break['id'], 'minutes' => 20])->assertSessionHasNoErrors();
        $resized = $this->day($ctx['school']);
        $this->assertSame('11:20', $resized[count($resized) - 1]['end']);
        $this->write('/timetable/periods/reshape', ['operation' => 'remove_break', 'period_id' => $break['id']])->assertSessionHasNoErrors();
        $this->assertSame(['08:00', '08:45', '09:30', '10:15'], array_column($this->day($ctx['school']), 'start'));
        $this->assertSame(2, (int) DB::table('timetable.periods')->where('id', $break['id'])->value('status'), 'retired, never deleted');

        // Refusals: removing a lesson, a day past midnight.
        $this->write('/timetable/periods/reshape', ['operation' => 'remove_break', 'period_id' => $p1])->assertSessionHasErrors(['period' => 'timetable.break_not_found']);
        $this->write('/timetable/periods/reshape', ['operation' => 'fixed_pattern', 'start_time' => '22:00', 'minutes' => 60])->assertSessionHasErrors(['period' => 'timetable.day_past_midnight']);

        // A fixed pattern: every lesson 40 minutes from 07:30.
        $this->write('/timetable/periods/reshape', ['operation' => 'fixed_pattern', 'start_time' => '07:30', 'minutes' => 40])->assertSessionHasNoErrors();
        $this->assertSame(['07:30', '08:10', '08:50', '09:30'], array_column($this->day($ctx['school']), 'start'));
    }

    #[Test]
    public function display_settings_are_normalised_and_saved_per_school_year(): void
    {
        $ctx = $this->seedSchool('WB-F');

        $this->write('/timetable/display', ['academic_year_id' => $ctx['year'], 'display' => [
            'layout' => 'columns', 'font_family' => 'comic-sans', 'font_scale' => 120, 'fields' => ['room' => true, 'nonsense' => true], 'period_header' => ['clock' => '24'],
        ]])->assertSessionHas('success', 'flash.timetable.displaySaved');

        $this->get('/timetable?academic_year_id='.$ctx['year'])->assertInertia(fn ($page) => $page
            ->where('display.settings.layout', 'columns')
            ->where('display.settings.font_family', 'segoe')
            ->where('display.settings.font_scale', 120)
            ->where('display.settings.fields.room', true)
            ->missing('display.settings.fields.nonsense')
            ->where('display.settings.period_header.clock', '24')
            ->etc());
    }

    #[Test]
    public function the_test_report_runs_on_demand_with_causes_remedies_and_marks(): void
    {
        $ctx = $this->seedSchool('WB-T');

        $this->get('/timetable?academic_year_id='.$ctx['year'].'&test=1')->assertInertia(fn ($page) => $page
            ->missing('testReport')
            ->reloadOnly('testReport', fn ($reload) => $reload
                ->where('testReport.verdict', fn ($v) => in_array($v, ['ready', 'ready_with_issues', 'blocked'], true))
                ->has('testReport.counts.critical')
                ->has('testReport.categories.rooms')
                ->has('testReport.issues')
                ->etc()));

        // Two teachers share the abbreviation «ع» → a data warning; ignore it, then it is marked.
        DB::table('teachers.teachers')->whereIn('id', [$ctx['theoryTeacher'], $ctx['practicalTeacher']])->update(['abbreviation' => 'ع']);
        $report = app(TestTimetableHandler::class)->handle(new TestTimetableQuery($ctx['school'], $ctx['year']));
        $duplicate = collect($report['issues'])->firstWhere('code', 'duplicate_abbreviation');
        $this->assertNotNull($duplicate);
        $this->assertSame('warning', $duplicate['severity']);
        $this->assertSame('data', $duplicate['category']);

        $this->write('/timetable/test/marks', ['academic_year_id' => $ctx['year'], 'issue_key' => $duplicate['key'], 'mark' => 1])->assertSessionHasNoErrors();
        $again = app(TestTimetableHandler::class)->handle(new TestTimetableQuery($ctx['school'], $ctx['year']));
        $this->assertSame('ignored', collect($again['issues'])->firstWhere('key', $duplicate['key'])['mark']);
        $this->write('/timetable/test/marks', ['academic_year_id' => $ctx['year'], 'issue_key' => $duplicate['key'], 'mark' => null])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('timetable.test_marks')->where('issue_key', $duplicate['key'])->count(), 'cleared by status, never deleted');
    }

    #[Test]
    public function teachers_are_imported_from_a_spreadsheet_after_preview_and_confirmation(): void
    {
        $ctx = $this->seedSchool('WB-I');

        $this->get('/imports/templates/teachers')->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $csv = "الرقم الوظيفي,الاسم,اللقب,اللقب العلمي,الاختصار,تاريخ التعيين\n"
            ."IMP-1,نور الهدى,الموصلي,مدرس,نور,1/9/2015\n"
            ."IMP-1,مكرر,س,,,\n"
            ."EMP-T-WB-I,علي,حسن,مهندس,علي ح,\n"
            .",,,دكتور,,32/1/2020\n";
        $this->from('/imports')->withHeader('X-Idempotency-Key', 'up-'.(++$this->key))
            ->post('/imports/teachers', ['file' => UploadedFile::fake()->createWithContent('teachers.csv', $csv), 'academic_year_id' => $ctx['year']])
            ->assertSessionHas('success', 'flash.imports.uploaded');

        $batch = DB::table('documents.import_batches')->where('school_id', $ctx['school'])->orderByDesc('id')->first();
        $this->assertSame(2, (int) $batch->status, (string) $batch->error);
        $this->assertSame([4, 2, 1, 1], [(int) $batch->total_rows, (int) $batch->valid_rows, (int) $batch->error_rows, (int) $batch->duplicate_rows]);
        $this->assertSame(0, DB::table('teachers.teachers')->where('employee_code', 'IMP-1')->count(), 'nothing imported before confirmation');

        $this->get('/imports?batch='.$batch->id)->assertInertia(fn ($page) => $page->component('imports/index')->where('batch.id', (int) $batch->id)->has('rows.rows', 4)->etc());
        $this->get('/imports/batches/'.$batch->id.'/errors')->assertOk();

        $this->from('/imports')->withHeader('X-Idempotency-Key', 'commit-'.(++$this->key))->post('/imports/batches/'.$batch->id.'/commit')
            ->assertSessionHas('success', 'flash.imports.committing');
        $done = DB::table('documents.import_batches')->where('id', $batch->id)->first();
        $this->assertSame(4, (int) $done->status);
        $result = json_decode((string) $done->result, true);
        $this->assertEquals(['created' => 1, 'updated' => 1, 'skipped' => 1, 'failed' => 0], array_intersect_key($result, array_flip(['created', 'updated', 'skipped', 'failed'])));
        $imported = DB::table('teachers.teachers')->where('employee_code', 'IMP-1')->first();
        $this->assertSame('نور', $imported->abbreviation);
        $this->assertSame('2015-09-01', substr((string) $imported->hire_date, 0, 10));
        $this->assertSame('علي ح', DB::table('teachers.teachers')->where('id', $ctx['theoryTeacher'])->value('abbreviation'));

        // A second commit of the same batch is refused.
        $this->from('/imports')->withHeader('X-Idempotency-Key', 'commit-'.(++$this->key))->post('/imports/batches/'.$batch->id.'/commit')
            ->assertSessionHasErrors(['import' => 'import.batch_not_previewed']);
    }

    private function write(string $url, array $data): TestResponse
    {
        return $this->from('/timetable')->withHeader('X-Idempotency-Key', 'wb-'.(++$this->key))->post($url, $data);
    }

    private function patchWrite(string $url, array $data): TestResponse
    {
        return $this->from('/timetable')->withHeader('X-Idempotency-Key', 'wb-'.(++$this->key))->patch($url, $data);
    }

    /** @return list<array{id: int, start: string, end: string, type: int, name: string|null, show_in: int}> the active day */
    private function day(int $schoolId): array
    {
        return DB::table('timetable.periods')->where('school_id', $schoolId)->where('status', 1)->orderBy('period_number')
            ->get(['id', 'start_time', 'end_time', 'period_type', 'name', 'show_in'])
            ->map(static fn (object $p): array => ['id' => (int) $p->id, 'start' => substr((string) $p->start_time, 0, 5), 'end' => substr((string) $p->end_time, 0, 5),
                'type' => (int) $p->period_type, 'name' => $p->name, 'show_in' => (int) $p->show_in])->all();
    }

    /** @return array<string, mixed> one vocational school: branch › department, a class with sections A / B, two teachers, four periods */
    private function seedSchool(string $tag): array
    {
        $schoolId = $this->createSchool('SCH-'.$tag, 'School '.$tag);
        $yearId = $this->createAcademicYear('AY-'.$tag);
        $gradeId = $this->createGradeLevel('G-'.$tag);
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId, 'code' => 'BR-'.$tag, 'name' => 'الصناعي', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $departmentId = $this->createDepartmentForSchool($schoolId, 'الكهرباء', $branchId);
        $subject = static fn (string $code, string $name, int $type): int => (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code.'-'.$tag, 'name' => $name, 'name_en' => $code.'-'.$tag, 'subject_type' => $type, 'credit_hours' => 3,
            'max_grade' => 100, 'pass_grade' => 50, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $theory = $subject('ELT', 'كهرباء نظري', 1);
        $practical = $subject('ELP', 'كهرباء عملي', 3);
        $curriculumId = $this->createCurriculumWithSubjects($schoolId, $yearId, $gradeId, [$theory, $practical], null, $departmentId);
        DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->where('curriculum_id', $curriculumId)->update(['weekly_hours' => 2]);
        $class = $this->createClassForSchool($schoolId, $yearId, $gradeId);
        $sectionA = (int) $this->createSectionForClass((int) $class->id)->id;
        $this->createSectionForClass((int) $class->id);

        // Every role before the first request (permissions are cached per user once read).
        $user = $this->actingAsTeachersManagerForSchool($schoolId);
        $this->actingAsTimetableManagerForSchool($schoolId, $user);
        $this->actingAsCurriculumManagerForSchool($schoolId, $user);
        $this->actingAsEnrollmentManagerForSchool($schoolId, $user);
        app(SecurityPermissionSeeder::class)->grantSchoolManager($user, $schoolId);
        $teacher = function (string $code, string $name, int $subjectId) use ($yearId, $branchId, $departmentId, $class): int {
            $this->from('/teachers')->withHeader('X-Idempotency-Key', 'register-'.$code)
                ->post('/teachers', ['academic_year_id' => $yearId, 'employee_code' => $code, 'first_name' => $name, 'last_name' => 'حسن'])
                ->assertSessionHas('success', 'flash.teachers.registered');
            $id = (int) DB::table('teachers.teachers')->where('employee_code', $code)->value('id');
            $this->from('/teachers')->withHeader('X-Idempotency-Key', 'assign-'.$code)
                ->post('/teachers/'.$id.'/assignments', ['academic_year_id' => $yearId, 'subject_id' => $subjectId, 'branch_id' => $branchId,
                    'department_id' => $departmentId, 'class_id' => (int) $class->id, 'section_id' => null])
                ->assertSessionHas('success', 'flash.teachers.assignmentAdded');

            return $id;
        };
        $theoryTeacher = $teacher('EMP-T-'.$tag, 'علي', $theory);
        $practicalTeacher = $teacher('EMP-P-'.$tag, 'سعيد', $practical);

        $periods = [];
        foreach ([['08:00', '08:45'], ['08:45', '09:30'], ['09:30', '10:15'], ['10:15', '11:00']] as $n => [$start, $end]) {
            $this->from('/timetable')->withHeader('X-Idempotency-Key', 'period-'.$tag.'-'.$n)
                ->post('/timetable/periods', ['period_number' => $n + 1, 'start_time' => $start, 'end_time' => $end, 'period_type' => 1])
                ->assertSessionHasNoErrors();
            $periods[] = (int) DB::table('timetable.periods')->where('school_id', $schoolId)->where('period_number', $n + 1)->value('id');
        }

        return [
            'school' => $schoolId, 'year' => $yearId, 'branch' => $branchId, 'class' => (int) $class->id, 'sectionA' => $sectionA,
            'theory' => $theory, 'practical' => $practical, 'theoryTeacher' => $theoryTeacher, 'practicalTeacher' => $practicalTeacher,
            'periods' => $periods, 'user' => $user,
        ];
    }
}
