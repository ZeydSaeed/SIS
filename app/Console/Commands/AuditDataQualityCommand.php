<?php

namespace App\Console\Commands;

use App\Database\SchemaHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only data-quality audit for admission · students · enrollment · teachers · curriculum · timetable.
 *
 * 1. Integrity checks — every query returns the offending rows (must be 0).
 * 2. Field fill — per column, how many rows are NULL / empty, so a missing field is visible at a glance.
 *
 * php artisan sis:audit-data-quality [--fill] [--only=students,teachers]
 */
class AuditDataQualityCommand extends Command
{
    protected $signature = 'sis:audit-data-quality
                            {--fill : Also print the per-column fill report}
                            {--only= : Comma list of tables (schema.table) for the fill report}';

    protected $description = 'Integrity checks + per-column fill report for the admission → timetable data';

    /** @var list<string> */
    private const FILL_TABLES = [
        'admission.application_periods', 'admission.applications', 'admission.application_documents',
        'students.students', 'students.student_documents',
        'enrollment.classes', 'enrollment.sections', 'enrollment.enrollments', 'enrollment.enrollment_subjects',
        'organization.branches', 'organization.departments',
        'curriculum.subjects', 'curriculum.curricula', 'curriculum.curriculum_subjects',
        'vocational.specializations',
        'teachers.teachers', 'teachers.teacher_schools', 'teachers.teacher_subjects', 'teachers.teaching_assignments',
        'timetable.periods', 'timetable.schedules',
    ];

    public function handle(): int
    {
        DB::statement("SELECT set_config('app.current_school_id', '', false)");

        $failed = $this->integrity();
        if ($this->option('fill') || $this->option('only')) {
            $this->fill();
        }

        $failed === 0
            ? $this->components->info('All integrity checks passed.')
            : $this->components->error($failed.' integrity check(s) failed.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function integrity(): int
    {
        $failed = 0;
        $rows = [];
        foreach ($this->checks() as $label => $sql) {
            $n = (int) DB::selectOne("SELECT count(*) AS n FROM ({$sql}) q")->n;
            $rows[] = [$n === 0 ? 'OK' : 'FAIL', $label, $n];
            $failed += $n === 0 ? 0 : 1;
        }
        $this->table(['', 'Check', 'Offending'], $rows);

        return $failed;
    }

    /** @return array<string, string> label → SQL listing offending rows */
    private function checks(): array
    {
        $q = static fn (string $schema, string $table): string => SchemaHelper::qualified($schema, $table);
        $branches = $q('organization', 'branches');
        $departments = $q('organization', 'departments');

        return [
            // --- one spelling per concept
            'duplicate branch name in a school' => "SELECT school_id, name FROM {$branches} WHERE status = 1 GROUP BY 1, 2 HAVING count(*) > 1",
            'duplicate department name in a branch' => "SELECT branch_id, name FROM {$departments} WHERE status = 1 GROUP BY 1, 2 HAVING count(*) > 1",
            'department without a branch' => "SELECT id FROM {$departments} WHERE branch_id IS NULL",
            'duplicate section name in a class' => 'SELECT class_id, name FROM '.$q('enrollment', 'sections').' WHERE status = 1 GROUP BY 1, 2 HAVING count(*) > 1',
            'duplicate class name in a school-year' => 'SELECT school_id, academic_year_id, name FROM '.$q('enrollment', 'classes').' GROUP BY 1, 2, 3 HAVING count(*) > 1',
            'duplicate grade level name' => 'SELECT name FROM '.$q('academic', 'grade_levels').' GROUP BY 1 HAVING count(*) > 1',
            'duplicate subject name' => 'SELECT name FROM '.$q('curriculum', 'subjects').' GROUP BY 1 HAVING count(*) > 1',
            'department without exactly one specialization' => "SELECT d.id FROM {$departments} d WHERE d.status = 1 AND d.school_id = (SELECT min(school_id) FROM {$departments}) "
                .'AND (SELECT count(*) FROM '.$q('vocational', 'specializations').' s WHERE s.department_id = d.id AND s.status = 1) <> 1',

            // --- students / enrollment
            'student without national_id or birth_date' => 'SELECT id FROM '.$q('students', 'students').' WHERE national_id IS NULL OR birth_date IS NULL',
            'duplicate student national_id' => 'SELECT national_id FROM '.$q('students', 'students').' WHERE status <> 0 GROUP BY 1 HAVING count(*) > 1',
            'student full_name differs from its parts' => 'SELECT id FROM '.$q('students', 'students').' WHERE full_name IS NULL OR btrim(full_name) = \'\'',
            // Waiting for a seat (accepted, section full / not yet placed) or an ended enrollment (cancelled) are legitimate.
            'active student never placed and not waiting for a seat' => 'SELECT s.id FROM '.$q('students', 'students').' s WHERE s.status = 1 AND NOT EXISTS (SELECT 1 FROM '.$q('enrollment', 'enrollments').' e WHERE e.student_id = s.id) AND NOT EXISTS (SELECT 1 FROM '.$q('admission', 'applications').' a WHERE a.student_id = s.id AND (a.notes LIKE \'%بانتظار%\' OR a.notes LIKE \'%ممتلئة%\'))',
            'student with two active enrollments in one year' => 'SELECT student_id, academic_year_id FROM '.$q('enrollment', 'enrollments').' WHERE status = 1 GROUP BY 1, 2 HAVING count(*) > 1',
            'enrollment section not in its class' => 'SELECT e.id FROM '.$q('enrollment', 'enrollments').' e JOIN '.$q('enrollment', 'sections').' s ON s.id = e.section_id WHERE s.class_id <> e.class_id',
            'enrollment department not in its branch' => 'SELECT e.id FROM '.$q('enrollment', 'enrollments').' e JOIN '.$departments.' d ON d.id = e.department_id WHERE e.branch_id IS DISTINCT FROM d.branch_id',
            'active enrollment without branch or department' => 'SELECT id FROM '.$q('enrollment', 'enrollments').' WHERE status = 1 AND (branch_id IS NULL OR department_id IS NULL)',
            'section over its capacity' => 'SELECT s.id FROM '.$q('enrollment', 'sections').' s WHERE s.capacity IS NOT NULL AND (SELECT count(*) FROM '.$q('enrollment', 'enrollments').' e WHERE e.section_id = s.id AND e.status = 1) > s.capacity',
            'section without capacity' => 'SELECT id FROM '.$q('enrollment', 'sections').' WHERE capacity IS NULL',
            'student placement differs from its active enrollment' => 'SELECT s.id FROM '.$q('students', 'students').' s JOIN '.$q('enrollment', 'enrollments').' e ON e.student_id = s.id AND e.status = 1 WHERE s.department_id IS DISTINCT FROM e.department_id OR s.branch_id IS DISTINCT FROM e.branch_id',

            // --- admission
            'application without a period / student link of an accepted one' => 'SELECT id FROM '.$q('admission', 'applications').' WHERE status = 6 AND student_id IS NULL',
            'duplicate application national_id in one period' => 'SELECT application_period_id, national_id FROM '.$q('admission', 'applications').' WHERE national_id IS NOT NULL GROUP BY 1, 2 HAVING count(*) > 1',

            'application names a department but has no department_id' => 'SELECT id FROM '.$q('admission', 'applications').' WHERE department_id IS NULL AND department_name IS NOT NULL AND btrim(department_name) <> \'\'',
            'application department not in its branch' => 'SELECT a.id FROM '.$q('admission', 'applications').' a JOIN '.$departments.' d ON d.id = a.department_id WHERE a.branch_id IS DISTINCT FROM d.branch_id',

            'application specialization differs from the mirror of its department' => 'SELECT a.id FROM '.$q('admission', 'applications').' a JOIN '.$q('vocational', 'specializations').' s ON s.department_id = a.department_id AND s.school_id = a.school_id AND s.status = 1 WHERE a.specialization_id IS DISTINCT FROM s.id',
            'curriculum without the specialization mirrored from its department' => 'SELECT c.id FROM '.$q('curriculum', 'curricula').' c JOIN '.$q('vocational', 'specializations').' s ON s.department_id = c.department_id AND s.school_id = c.school_id AND s.status = 1 WHERE c.status = 1 AND c.specialization_id IS NULL',

            // --- curriculum
            'curriculum without subjects' => 'SELECT c.id FROM '.$q('curriculum', 'curricula').' c WHERE NOT EXISTS (SELECT 1 FROM '.$q('curriculum', 'curriculum_subjects').' cs WHERE cs.curriculum_id = c.id)',
            'curriculum subject without weekly hours' => 'SELECT id FROM '.$q('curriculum', 'curriculum_subjects').' WHERE status = 1 AND (weekly_hours IS NULL OR weekly_hours < 1)',
            'two active curricula for one department × grade × year' => 'SELECT department_id, grade_level_id, academic_year_id FROM '.$q('curriculum', 'curricula').' WHERE status = 1 AND department_id IS NOT NULL GROUP BY 1, 2, 3 HAVING count(*) > 1',
            'class department has no curriculum for the grade' => 'SELECT DISTINCT e.department_id, cl.grade_level_id FROM '.$q('enrollment', 'enrollments').' e JOIN '.$q('enrollment', 'classes').' cl ON cl.id = e.class_id WHERE e.status = 1 AND NOT EXISTS (SELECT 1 FROM '.$q('curriculum', 'curricula').' c WHERE c.department_id = e.department_id AND c.grade_level_id = cl.grade_level_id AND c.academic_year_id = e.academic_year_id AND c.status = 1)',

            // --- teachers
            'teacher without national_id / specialization / hire date' => 'SELECT id FROM '.$q('teachers', 'teachers').' WHERE national_id IS NULL OR specialization_field IS NULL OR hire_date IS NULL',
            'duplicate teacher national_id' => 'SELECT national_id FROM '.$q('teachers', 'teachers').' GROUP BY 1 HAVING count(*) > 1',
            'teacher full_name empty' => 'SELECT id FROM '.$q('teachers', 'teachers').' WHERE full_name IS NULL OR btrim(full_name) = \'\'',
            'teacher without a school membership' => 'SELECT t.id FROM '.$q('teachers', 'teachers').' t WHERE NOT EXISTS (SELECT 1 FROM '.$q('teachers', 'teacher_schools').' ts WHERE ts.teacher_id = t.id)',
            'teacher school without employment type' => 'SELECT id FROM '.$q('teachers', 'teacher_schools').' WHERE employment_type IS NULL',
            'active teaching assignment for a subject the teacher does not hold' => 'SELECT ta.id FROM '.$q('teachers', 'teaching_assignments').' ta WHERE ta.status = 1 AND NOT EXISTS (SELECT 1 FROM '.$q('teachers', 'teacher_subjects').' ts WHERE ts.teacher_id = ta.teacher_id AND ts.subject_id = ta.subject_id AND ts.academic_year_id = ta.academic_year_id AND ts.school_id = ta.school_id)',
            'teaching assignment department not in its branch' => 'SELECT ta.id FROM '.$q('teachers', 'teaching_assignments').' ta JOIN '.$departments.' d ON d.id = ta.department_id WHERE d.branch_id <> ta.branch_id',
            'teaching assignment section not in its class' => 'SELECT ta.id FROM '.$q('teachers', 'teaching_assignments').' ta JOIN '.$q('enrollment', 'sections').' s ON s.id = ta.section_id WHERE s.class_id IS DISTINCT FROM ta.class_id',
            'section homeroom teacher inactive or unknown' => 'SELECT s.id FROM '.$q('enrollment', 'sections').' s WHERE s.homeroom_teacher_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM '.$q('teachers', 'teachers').' t WHERE t.id = s.homeroom_teacher_id AND t.status = 1)',

            // --- timetable
            'teacher double-booked' => 'SELECT teacher_id, day_of_week, period_id FROM '.$q('timetable', 'schedules').' WHERE lifecycle_status = 1 AND week_no IS NULL GROUP BY 1, 2, 3 HAVING count(*) > 1',
            'section double-booked' => 'SELECT section_id, day_of_week, period_id FROM '.$q('timetable', 'schedules').' WHERE lifecycle_status = 1 AND week_no IS NULL AND group_id IS NULL GROUP BY 1, 2, 3 HAVING count(*) > 1',
            'lesson of an inactive teacher' => 'SELECT s.id FROM '.$q('timetable', 'schedules').' s JOIN '.$q('teachers', 'teachers').' t ON t.id = s.teacher_id WHERE s.lifecycle_status = 1 AND t.status <> 1',
            'lesson outside the school day' => 'SELECT s.id FROM '.$q('timetable', 'schedules').' s LEFT JOIN '.$q('timetable', 'periods').' p ON p.id = s.period_id AND p.school_id = s.school_id WHERE p.id IS NULL',
        ];
    }

    private function fill(): void
    {
        $only = array_filter(array_map('trim', explode(',', (string) $this->option('only'))));
        foreach (self::FILL_TABLES as $table) {
            if ($only !== [] && ! in_array($table, $only, true)) {
                continue;
            }
            [$schema, $name] = explode('.', $table);
            $total = (int) DB::selectOne("SELECT count(*) AS n FROM \"{$schema}\".\"{$name}\"")->n;
            $columns = DB::select('SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position', [$schema, $name]);
            $gaps = [];
            foreach ($columns as $column) {
                $col = '"'.$column->column_name.'"';
                $isText = in_array($column->data_type, ['character varying', 'text'], true);
                $empty = (int) DB::selectOne("SELECT count(*) AS n FROM \"{$schema}\".\"{$name}\" WHERE {$col} IS NULL".($isText ? " OR btrim({$col}) = ''" : ''))->n;
                if ($empty > 0) {
                    $gaps[] = [$column->column_name, $empty.' / '.$total, $total > 0 ? round($empty / $total * 100).'%' : '-'];
                }
            }
            $this->components->twoColumnDetail("<fg=cyan>{$table}</>", "{$total} rows".($gaps === [] ? ' — all fields filled' : ''));
            if ($gaps !== []) {
                $this->table(['Column', 'Empty', '%'], $gaps);
            }
        }
    }
}
