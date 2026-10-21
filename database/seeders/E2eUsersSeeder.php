<?php

namespace Database\Seeders;

use App\Application\Teachers\Commands\UpdateTeacherCommand;
use App\Application\Teachers\Commands\UpdateTeacherHandler;
use App\Database\SchemaHelper;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The end-to-end personas (synthetic accounts, password from SIS_E2E_PASSWORD — never stored in the repository):
 *
 * - admin     every role in اعدادية التحرير (the first user, so demo seeders that act as «the administrator» use it);
 * - school2   every role in اعدادية العباس only — must never see اعدادية التحرير;
 * - viewer    the *_viewer roles in اعدادية التحرير — reads, never writes;
 * - teacher   attendance teacher + leave requester, linked to teacher T-S001 (who has lessons on the timetable);
 * - noroles   signed in, no role at all — every module page must refuse.
 *
 * Every other account gets a random password, so no default password («password») is left usable. Re-running resets
 * the personas' roles to exactly this set.
 */
final class E2eUsersSeeder extends Seeder
{
    public const PERSONAS = [
        'admin' => ['email' => 'e2e-admin@sis.test', 'name' => 'مدير الاختبار'],
        'school2' => ['email' => 'e2e-school2@sis.test', 'name' => 'مدير مدرسة العباس'],
        'viewer' => ['email' => 'e2e-viewer@sis.test', 'name' => 'مستعرض الاختبار'],
        'teacher' => ['email' => 'e2e-teacher@sis.test', 'name' => 'معلم الاختبار'],
        'noroles' => ['email' => 'e2e-noroles@sis.test', 'name' => 'مستخدم بلا صلاحيات'],
    ];

    private const SCHOOL = 'اعدادية التحرير';

    private const SECOND_SCHOOL = 'اعدادية العباس';

    private const TEACHER_CODE = 'T-S001';

    /** @var array<string, int> persona → user id */
    private array $ids = [];

    public function run(): void
    {
        $password = (string) env('SIS_E2E_PASSWORD', '');
        if (mb_strlen($password) < 12) {
            throw new RuntimeException('SIS_E2E_PASSWORD (12+ characters) is required to create the e2e personas.');
        }
        $schools = DB::table(SchemaHelper::qualified('organization', 'schools'))->pluck('id', 'name');
        $school = (int) ($schools[self::SCHOOL] ?? throw new RuntimeException('Missing school '.self::SCHOOL));
        $second = (int) ($schools[self::SECOND_SCHOOL] ?? throw new RuntimeException('Missing school '.self::SECOND_SCHOOL));

        $hash = Hash::make($password);
        $first = User::query()->orderBy('id')->first();
        foreach (self::PERSONAS as $key => $persona) {
            $user = User::query()->where('email', $persona['email'])->first()
                ?? ($key === 'admin' && $first !== null && ! str_ends_with((string) $first->email, '@sis.test') ? $first : null)
                ?? new User;
            $user->forceFill([
                'name' => $persona['name'],
                'email' => $persona['email'],
                'password' => $hash,
                'email_verified_at' => now(),
            ])->save();
            $this->ids[$key] = (int) $user->id;
        }

        // Any other account: a random password nobody knows.
        User::query()->whereNotIn('id', array_values($this->ids))->get()
            ->each(static fn (User $u) => $u->forceFill(['password' => Hash::make(Str::random(40))])->save());

        DB::table(SchemaHelper::qualified('security', 'user_roles'))->whereIn('user_id', array_values($this->ids))->delete();
        $roles = app(SecurityPermissionSeeder::class);
        $all = array_keys(config('security.roles', []));
        foreach ($all as $role) {
            $roles->assignRole($this->user('admin'), $role, $school);
            $roles->assignRole($this->user('school2'), $role, $second);
        }
        foreach ($all as $role) {
            if (str_ends_with($role, '_viewer')) {
                $roles->assignRole($this->user('viewer'), $role, $school);
            }
        }
        foreach (['attendance_teacher', 'leave_requester'] as $role) {
            $roles->assignRole($this->user('teacher'), $role, $school);
        }

        foreach ($this->ids as $id) {
            Cache::forget("security.permissions.user.{$id}");
            Cache::forget("security.schools.user.{$id}");
        }
        $this->command?->info('E2eUsersSeeder: '.count($this->ids).' personas ready ('.implode(', ', array_keys($this->ids)).').');
    }

    public function adminId(): int
    {
        return $this->ids['admin'] ?? throw new RuntimeException('Personas not seeded yet.');
    }

    /** Teacher T-S001 signs in as the «teacher» persona (through the teachers module's own update). */
    public function linkTeacherPersona(): void
    {
        $schoolId = (int) DB::table(SchemaHelper::qualified('organization', 'schools'))->where('name', self::SCHOOL)->value('id');
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [(string) $schoolId]);
        $teacher = DB::table(SchemaHelper::qualified('teachers', 'teachers'))->where('employee_code', self::TEACHER_CODE)->first();
        if ($teacher === null) {
            throw new RuntimeException('Teacher '.self::TEACHER_CODE.' is missing.');
        }
        $result = app(UpdateTeacherHandler::class)->handle(new UpdateTeacherCommand(
            schoolId: $schoolId,
            teacherId: (int) $teacher->id,
            firstName: (string) $teacher->first_name,
            lastName: (string) $teacher->last_name,
            nationalId: $teacher->national_id,
            specializationField: $teacher->specialization_field,
            hireDate: $teacher->hire_date,
            userId: $this->ids['teacher'],
            idempotencyKey: 'e2e-users:link-teacher:'.$this->ids['teacher'].':'.$teacher->id,
            fatherName: $teacher->father_name,
            grandfatherName: $teacher->grandfather_name,
        ));
        if ($result->failed()) {
            throw new RuntimeException('Linking the teacher persona failed — '.implode(', ', $result->errors));
        }
    }

    private function user(string $key): User
    {
        return User::query()->findOrFail($this->ids[$key]);
    }
}
