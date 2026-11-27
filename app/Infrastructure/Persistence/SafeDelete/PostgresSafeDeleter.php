<?php

namespace App\Infrastructure\Persistence\SafeDelete;

use App\Application\Contracts\SafeDeleter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * «حذف آمن» (owner's decision, 2026-11-27): a real DELETE, allowed only when nothing else points at the record.
 *
 * Inside one transaction it sets `sis.safe_delete = on` (the no-delete triggers let a DELETE through only with
 * it — see SafeDeleteTriggerBypass), deletes the rows the record owns outright, then the record. Any other row
 * that still references it trips a foreign key (SQLSTATE 23503 / 23001): everything rolls back and the delete is
 * refused, naming the referencing table.
 *
 * Specs use `{id}` / `{school}` placeholders, bound as query parameters; the SQL text itself is constant.
 */
final class PostgresSafeDeleter implements SafeDeleter
{
    /**
     * @var array<string, array{
     *     table: string,
     *     scope: string,
     *     elsewhere?: array{sql: string, linked_to: string},
     *     owned: list<string>
     * }>
     */
    private const SPECS = [
        // Student of this school: placement (enrollments) and personal rows go with it; grades, attendance,
        // results, finance, certificates, transfers… keep it.
        'student' => [
            'table' => 'students.students',
            'scope' => 'school_id = {school}',
            'owned' => [
                'DELETE FROM enrollment.enrollment_subjects WHERE enrollment_id IN (SELECT id FROM enrollment.enrollments WHERE student_id = {id})',
                'DELETE FROM enrollment.enrollments WHERE student_id = {id}',
                'DELETE FROM guardians.student_guardians WHERE student_id = {id}',
                'DELETE FROM students.student_addresses WHERE student_id = {id}',
                'DELETE FROM students.student_contacts WHERE student_id = {id}',
                'DELETE FROM students.student_documents WHERE student_id = {id}',
            ],
        ],
        // Teacher of this school (and of no other school still): qualifications, school links, subject links,
        // assignments and availability go with it; schedules, attendance sessions, leave… keep it.
        'teacher' => [
            'table' => 'teachers.teachers',
            'scope' => 'EXISTS (SELECT 1 FROM teachers.teacher_schools ts WHERE ts.teacher_id = {id} AND ts.school_id = {school})',
            'elsewhere' => [
                'sql' => 'SELECT 1 FROM teachers.teacher_schools ts WHERE ts.teacher_id = {id} AND ts.school_id <> {school} AND ts.left_at IS NULL',
                'linked_to' => 'other_school',
            ],
            'owned' => [
                'DELETE FROM timetable.availability WHERE teacher_id = {id}',
                'DELETE FROM teachers.teaching_assignments WHERE teacher_id = {id}',
                'DELETE FROM teachers.teacher_subjects WHERE teacher_id = {id}',
                'DELETE FROM teachers.teacher_qualifications WHERE teacher_id = {id}',
                'DELETE FROM teachers.teacher_schools WHERE teacher_id = {id}',
            ],
        ],
        // Subject (shared catalogue): its prerequisite rows go with it; any curriculum, teacher, grade, timetable…
        // that uses it keeps it.
        'subject' => [
            'table' => 'curriculum.subjects',
            'scope' => 'TRUE',
            'owned' => [
                'DELETE FROM curriculum.prerequisites WHERE subject_id = {id} OR prerequisite_subject_id = {id}',
                'DELETE FROM curriculum.subject_types WHERE subject_id = {id}',
            ],
        ],
        // «حذف من المناهج وحذف المادة»: the owner confirmed the warning — the subject's lines in this school's
        // curricula go with it, with the teachers set on those lines and the subject's «المواد المسندة» rows in this
        // school. A line in another school's curriculum, a teacher assigned on «المعلمون», a grade, the timetable…
        // still keeps it.
        'subject_detach' => [
            'table' => 'curriculum.subjects',
            'scope' => 'TRUE',
            'owned' => [
                'DELETE FROM teachers.teaching_assignments WHERE school_id = {school} AND curriculum_subject_id IN (SELECT cs.id FROM curriculum.curriculum_subjects cs JOIN curriculum.curricula c ON c.id = cs.curriculum_id WHERE cs.subject_id = {id} AND c.school_id = {school})',
                'DELETE FROM teachers.teacher_subjects ts WHERE ts.subject_id = {id} AND ts.school_id = {school} AND NOT EXISTS (SELECT 1 FROM teachers.teaching_assignments ta WHERE ta.teacher_id = ts.teacher_id AND ta.subject_id = ts.subject_id AND ta.school_id = ts.school_id)',
                'DELETE FROM curriculum.curriculum_subjects WHERE subject_id = {id} AND curriculum_id IN (SELECT id FROM curriculum.curricula WHERE school_id = {school})',
                'DELETE FROM curriculum.prerequisites WHERE subject_id = {id} OR prerequisite_subject_id = {id}',
                'DELETE FROM curriculum.subject_types WHERE subject_id = {id}',
            ],
        ],
        // Curriculum of this school: its subject lines and the teachers set on them go with it — and a teacher's
        // «المواد المسندة» row that only this curriculum gave (no other teaching assignment, no timetable lesson).
        // The subjects themselves stay in «المواد».
        'curriculum' => [
            'table' => 'curriculum.curricula',
            'scope' => 'school_id = {school}',
            'owned' => [
                'DELETE FROM teachers.teacher_subjects ts WHERE EXISTS (SELECT 1 FROM teachers.teaching_assignments ta JOIN curriculum.curriculum_subjects cs ON cs.id = ta.curriculum_subject_id WHERE cs.curriculum_id = {id} AND ta.teacher_id = ts.teacher_id AND ta.subject_id = ts.subject_id AND ta.academic_year_id = ts.academic_year_id AND ta.school_id = ts.school_id)'
                    .' AND NOT EXISTS (SELECT 1 FROM teachers.teaching_assignments ta WHERE ta.teacher_id = ts.teacher_id AND ta.subject_id = ts.subject_id AND ta.academic_year_id = ts.academic_year_id AND ta.school_id = ts.school_id AND (ta.curriculum_subject_id IS NULL OR ta.curriculum_subject_id NOT IN (SELECT id FROM curriculum.curriculum_subjects WHERE curriculum_id = {id})))'
                    .' AND NOT EXISTS (SELECT 1 FROM timetable.schedules s WHERE ts.teacher_id IN (s.teacher_id, s.co_teacher_id) AND s.subject_id = ts.subject_id AND s.academic_year_id = ts.academic_year_id AND s.school_id = ts.school_id)',
                'DELETE FROM teachers.teaching_assignments WHERE curriculum_subject_id IN (SELECT id FROM curriculum.curriculum_subjects WHERE curriculum_id = {id})',
                'DELETE FROM curriculum.curriculum_subjects WHERE curriculum_id = {id}',
            ],
        ],
        // One subject line of a curriculum of this school, with the teachers set on it (and, as above, the
        // «المواد المسندة» rows only this line gave).
        'curriculum_subject' => [
            'table' => 'curriculum.curriculum_subjects',
            'scope' => 'EXISTS (SELECT 1 FROM curriculum.curricula c WHERE c.id = curriculum_subjects.curriculum_id AND c.school_id = {school})',
            'owned' => [
                'DELETE FROM teachers.teacher_subjects ts WHERE EXISTS (SELECT 1 FROM teachers.teaching_assignments ta WHERE ta.curriculum_subject_id = {id} AND ta.teacher_id = ts.teacher_id AND ta.subject_id = ts.subject_id AND ta.academic_year_id = ts.academic_year_id AND ta.school_id = ts.school_id)'
                    .' AND NOT EXISTS (SELECT 1 FROM teachers.teaching_assignments ta WHERE ta.teacher_id = ts.teacher_id AND ta.subject_id = ts.subject_id AND ta.academic_year_id = ts.academic_year_id AND ta.school_id = ts.school_id AND ta.curriculum_subject_id IS DISTINCT FROM CAST({id} AS bigint))'
                    .' AND NOT EXISTS (SELECT 1 FROM timetable.schedules s WHERE ts.teacher_id IN (s.teacher_id, s.co_teacher_id) AND s.subject_id = ts.subject_id AND s.academic_year_id = ts.academic_year_id AND s.school_id = ts.school_id)',
                'DELETE FROM teachers.teaching_assignments WHERE curriculum_subject_id = {id}',
            ],
        ],
        // Prerequisite rule between two subjects (shared catalogue).
        'prerequisite' => [
            'table' => 'curriculum.prerequisites',
            'scope' => 'TRUE',
            'owned' => [],
        ],
        // Branch of this school: refused while it still has departments (delete those first), students, rooms…
        'branch' => [
            'table' => 'organization.branches',
            'scope' => 'school_id = {school}',
            'owned' => [],
        ],
        'department' => [
            'table' => 'organization.departments',
            'scope' => 'school_id = {school}',
            'owned' => [],
        ],
        // Class of this school: its sections go with it (refused if enrolments, timetable… use one).
        'class' => [
            'table' => 'enrollment.classes',
            'scope' => 'school_id = {school}',
            'owned' => ['DELETE FROM enrollment.sections WHERE class_id = {id}'],
        ],
        'section' => [
            'table' => 'enrollment.sections',
            'scope' => 'EXISTS (SELECT 1 FROM enrollment.classes c WHERE c.id = sections.class_id AND c.school_id = {school})',
            'owned' => [],
        ],
        // Room of a branch of this school; room type of this school (shared types are not deletable here).
        'room' => [
            'table' => 'organization.rooms',
            'scope' => 'EXISTS (SELECT 1 FROM organization.branches b WHERE b.id = rooms.branch_id AND b.school_id = {school})',
            'owned' => [],
        ],
        'room_type' => [
            'table' => 'organization.room_types',
            'scope' => 'school_id = {school}',
            'owned' => [],
        ],
        // Directorate / school registry: refused while anything (a school, any school data) uses it.
        // Role assignments scoped to it are access rows, not school data: they go with it.
        'directorate' => [
            'table' => 'organization.directorates',
            'scope' => 'TRUE',
            'owned' => ['DELETE FROM security.user_roles WHERE directorate_id = {id}'],
        ],
        // Holiday of this school's calendar (shared / ministry holidays are not deletable here).
        'holiday' => [
            'table' => 'academic.holidays',
            'scope' => 'school_id = {school}',
            'owned' => [],
        ],
        // Leave type of this school: its policy versions go with it; requests / balances keep it.
        'leave_type' => [
            'table' => 'leave.types',
            'scope' => 'school_id = {school}',
            'owned' => ['DELETE FROM leave.policies WHERE leave_type_id = {id}'],
        ],
        // Admission period of this school: refused while applications use it.
        'admission_period' => [
            'table' => 'admission.application_periods',
            'scope' => 'school_id = {school}',
            'owned' => [],
        ],
        // Exam of this school: its sessions go with it; seated students or grades keep it.
        'exam' => [
            'table' => 'exams.exams',
            'scope' => 'school_id = {school}',
            'owned' => ['DELETE FROM exams.exam_sessions WHERE exam_id = {id}'],
        ],
        'exam_session' => [
            'table' => 'exams.exam_sessions',
            'scope' => 'school_id = {school}',
            'owned' => [],
        ],
        'school' => [
            'table' => 'organization.schools',
            'scope' => 'TRUE',
            'elsewhere' => ['sql' => 'SELECT 1 WHERE CAST({id} AS bigint) = CAST({school} AS bigint)', 'linked_to' => 'current_school'],
            'owned' => ['DELETE FROM security.user_roles WHERE school_id = {id}'],
        ],
    ];

    public function entities(): array
    {
        return array_keys(self::SPECS);
    }

    public function deleteMany(string $entity, int $schoolId, array $ids): array
    {
        try {
            return DB::transaction(function () use ($entity, $schoolId, $ids): array {
                foreach ($ids as $id) {
                    $outcome = $this->delete($entity, $schoolId, (int) $id);
                    if ($outcome['status'] !== 'deleted') {
                        // Undo the ones already deleted in this batch.
                        throw new SafeDeleteRefused($outcome + ['id' => (int) $id]);
                    }
                }

                return ['status' => 'deleted'];
            });
        } catch (SafeDeleteRefused $refused) {
            return $refused->outcome;
        }
    }

    public function delete(string $entity, int $schoolId, int $id): array
    {
        $spec = self::SPECS[$entity] ?? null;
        if ($spec === null) {
            return ['status' => 'not_found'];
        }
        // `{id}` / `{school}` → bound `?` parameters (never spliced into the SQL text).
        $bind = static function (string $sql) use ($id, $schoolId): array {
            preg_match_all('/\{(id|school)\}/', $sql, $m);
            $values = array_map(static fn (string $name): int => $name === 'id' ? $id : $schoolId, $m[1]);

            return [preg_replace('/\{(id|school)\}/', '?', $sql) ?? $sql, $values];
        };

        try {
            return DB::transaction(function () use ($spec, $id, $bind): array {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SET LOCAL sis.safe_delete = 'on'");
                }
                [$scopeSql, $scopeBindings] = $bind($spec['scope']);
                $found = DB::table($spec['table'])->where('id', $id)->whereRaw($scopeSql, $scopeBindings)->lockForUpdate()->first(['id']);
                if ($found === null) {
                    return ['status' => 'not_found'];
                }
                if (isset($spec['elsewhere'])) {
                    [$sql, $bindings] = $bind($spec['elsewhere']['sql']);
                    if (DB::selectOne($sql, $bindings) !== null) {
                        return ['status' => 'linked', 'linked_to' => $spec['elsewhere']['linked_to']];
                    }
                }
                foreach ($spec['owned'] as $owned) {
                    [$sql, $bindings] = $bind($owned);
                    DB::delete($sql, $bindings);
                }
                DB::table($spec['table'])->where('id', $id)->delete();
                // Off again at once: inside an outer transaction SET LOCAL would otherwise last until its end.
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("SET LOCAL sis.safe_delete = 'off'");
                }

                return ['status' => 'deleted'];
            });
        } catch (QueryException $e) {
            // 23503 foreign_key_violation (NO ACTION) · 23001 restrict_violation (ON DELETE RESTRICT).
            if (! in_array((string) ($e->errorInfo[0] ?? $e->getCode()), ['23503', '23001'], true)) {
                throw $e;
            }
            // «… violates foreign key constraint "…" on table "student_grades_ay_1"»
            preg_match_all('/on table "([^"]+)"/', $e->getMessage(), $m);

            return ['status' => 'linked', 'linked_to' => (string) (end($m[1]) ?: '')];
        }
    }
}
